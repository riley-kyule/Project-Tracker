<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    /**
     * Roles whose permission set is fixed in RoleSeeder on purpose — CEO and
     * Administrator are kept permission-identical there deliberately (an
     * explicit product decision, see the seeder), and that guarantee can't
     * survive being editable one role at a time from here.
     */
    private const LOCKED_ROLES = ['CEO', 'Administrator'];

    /**
     * Never offered as a togglable permission, on any role, from this screen:
     * it's what gates the screen itself, so granting it through the very UI
     * it protects would let a role hand itself (or another role) the power to
     * grant anything — a privilege-escalation path with no code review in the
     * loop. It stays grantable only by editing RoleSeeder.
     */
    private const UNGRANTABLE = ['permissions.manage'];

    /**
     * Plain-language name, one-line description, and category for every
     * permission RoleSeeder grants — the raw slug (e.g. "hr.leave.approve")
     * is never shown to the person using this screen. A permission that
     * exists in the database but is missing here (should never happen —
     * every seeded permission has an entry) falls back to its slug rather
     * than erroring the whole page.
     *
     * @return array<string, array{label: string, description: string, group: string}>
     */
    private function catalog(): array
    {
        return [
            'users.view' => ['label' => 'View user accounts', 'description' => 'See the list of everyone with an EWMS login.', 'group' => 'Company & users'],
            'users.manage' => ['label' => 'Manage user accounts', 'description' => 'Create logins, change roles, deactivate accounts.', 'group' => 'Company & users'],
            'departments.view' => ['label' => 'View departments', 'description' => 'See the company\'s department list and org structure.', 'group' => 'Company & users'],
            'departments.manage' => ['label' => 'Manage departments', 'description' => 'Create departments, set managers, reorganize teams.', 'group' => 'Company & users'],
            'boards.manage' => ['label' => 'Manage boards', 'description' => 'Create and configure Kanban boards and their columns.', 'group' => 'Work & tasks'],
            'tasks.create' => ['label' => 'Create tasks', 'description' => 'Add new tasks to a board.', 'group' => 'Work & tasks'],
            'labels.manage' => ['label' => 'Manage labels', 'description' => 'Create and edit the task labels available company-wide.', 'group' => 'Work & tasks'],
            'projects.manage' => ['label' => 'Manage projects', 'description' => 'Create and organize projects that group related boards.', 'group' => 'Work & tasks'],
            'reports.view' => ['label' => 'View task reports', 'description' => 'See company-wide task and workload reports.', 'group' => 'Reports & analytics'],
            'view marketing statistics' => ['label' => 'View marketing statistics', 'description' => 'See website traffic and search performance (GA4/Search Console).', 'group' => 'Reports & analytics'],
            'registry.manage' => ['label' => 'Manage website registry', 'description' => 'Add and configure the websites EWMS tracks.', 'group' => 'Reports & analytics'],
            'tickets.manage' => ['label' => 'Manage service desk tickets', 'description' => 'See, assign, and resolve support tickets for their team\'s queue (IT or R&D).', 'group' => 'Service desk'],
            'wordpress.manage' => ['label' => 'Manage WordPress users', 'description' => 'Connect sites and manage staff accounts across all connected WordPress sites.', 'group' => 'WordPress'],
            'mcp.manage' => ['label' => 'Manage the AI connector', 'description' => 'Generate and revoke tokens that let an AI assistant (Claude, ChatGPT) read company numbers.', 'group' => 'System'],
            'system.deploy' => ['label' => 'Deploy & system tools', 'description' => 'Trigger deployments, view the queue health and system report log.', 'group' => 'System'],
            'hr.employees.view' => ['label' => 'View employee records', 'description' => 'See employee profiles, contracts, and assigned assets.', 'group' => 'HR — people'],
            'hr.employees.manage' => ['label' => 'Manage employee records', 'description' => 'Edit employee profiles, contracts, and next-of-kin details.', 'group' => 'HR — people'],
            'hr.assets.view' => ['label' => 'View company assets', 'description' => 'See the equipment register and who has what.', 'group' => 'HR — people'],
            'hr.assets.manage' => ['label' => 'Manage company assets', 'description' => 'Add assets and assign or return them to employees.', 'group' => 'HR — people'],
            'hr.leave.view' => ['label' => 'View leave requests', 'description' => 'See employee leave balances and requests.', 'group' => 'HR — leave'],
            'hr.leave.manage' => ['label' => 'Manage leave', 'description' => 'Adjust leave balances and leave types.', 'group' => 'HR — leave'],
            'hr.leave.approve' => ['label' => 'Approve leave requests', 'description' => 'Approve or reject employees\' leave applications.', 'group' => 'HR — leave'],
            'hr.performance.view' => ['label' => 'View performance reviews', 'description' => 'See performance review cycles, goals, and ratings.', 'group' => 'HR — performance'],
            'hr.performance.manage' => ['label' => 'Manage performance reviews', 'description' => 'Create review cycles and record ratings.', 'group' => 'HR — performance'],
            'hr.compensation.view' => ['label' => 'View salaries', 'description' => 'See employees\' basic salary and allowances.', 'group' => 'HR — pay (sensitive)'],
            'hr.compensation.manage' => ['label' => 'Manage salaries', 'description' => 'Set or change employees\' basic salary and allowances.', 'group' => 'HR — pay (sensitive)'],
            'hr.payroll.view' => ['label' => 'View payroll', 'description' => 'See payroll runs and payslips.', 'group' => 'HR — pay (sensitive)'],
            'hr.payroll.process' => ['label' => 'Process payroll', 'description' => 'Run a payroll period and generate payslips.', 'group' => 'HR — pay (sensitive)'],
            'hr.payroll.approve' => ['label' => 'Approve & send payroll', 'description' => 'Give final sign-off on a payroll run and release payslips to staff.', 'group' => 'HR — pay (sensitive)'],
        ];
    }

    /** One plain-language sentence per role, shown as its summary — a role with no entry here (shouldn't happen) falls back to a generic line rather than breaking the page. */
    private function roleDescriptions(): array
    {
        return [
            'CEO' => 'Full access to everything in EWMS, including HR, payroll, and system settings.',
            'Administrator' => 'The same full access as CEO — kept as a separate role for day-to-day operational duties.',
            'HR Manager' => 'Runs HR day to day: employee records, leave, assets, and payroll — plus department-manager duties for their own team.',
            'HR Staff' => 'Manages employee records, leave, and assets. Cannot see salaries or payroll.',
            'Department Manager' => "Runs their own department's boards, tasks, and projects, and approves their team's leave.",
            'IT Technician' => 'Works the IT service desk queue.',
            'Research & Development' => 'Works the Research & Development service desk queue.',
            'Marketing' => 'Creates tasks and views marketing/website statistics.',
            'Customer Service' => 'Creates and works on day-to-day tasks.',
            'Employee' => 'Standard access — sees their own department and can create tasks.',
            'Viewer' => "Read-only access to their department. Can't create or change anything.",
        ];
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('permissions.manage'), 403);

        $catalog = $this->catalog();
        $descriptions = $this->roleDescriptions();

        $permissionNames = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, self::UNGRANTABLE, true))
            ->values();

        $permissions = $permissionNames->map(function (string $name) use ($catalog) {
            $entry = $catalog[$name] ?? ['label' => $name, 'description' => '', 'group' => 'Other'];

            return ['name' => $name, ...$entry];
        });

        $roles = Role::query()->with('permissions:id,name')->orderBy('name')->get();

        return Inertia::render('admin/permissions/index', [
            'permissions' => $permissions,
            'roles' => $roles->map(function (Role $role) use ($descriptions) {
                $members = User::query()
                    ->role($role->name)
                    ->where('status', User::STATUS_ACTIVE)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email']);

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $descriptions[$role->name] ?? 'A custom role — see its permissions below.',
                    'locked' => in_array($role->name, self::LOCKED_ROLES, true),
                    'permissions' => $role->permissions->pluck('name'),
                    'users' => $members,
                ];
            }),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('permissions.manage'), 403);
        abort_if(in_array($role->name, self::LOCKED_ROLES, true), 403, "{$role->name}'s permissions are fixed — see RoleSeeder.");

        $editable = Permission::query()
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, self::UNGRANTABLE, true))
            ->values();

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [Rule::in($editable)],
        ]);

        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $after = collect($validated['permissions'] ?? [])->sort()->values()->all();

        $role->syncPermissions($after);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($before !== $after) {
            AuditLogger::log($role, 'role.permissions.updated', ['permissions' => $before], ['permissions' => $after]);
        }

        return back();
    }
}
