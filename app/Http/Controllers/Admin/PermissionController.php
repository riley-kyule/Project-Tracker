<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('permissions.manage'), 403);

        $permissionNames = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, self::UNGRANTABLE, true))
            ->values();

        $roles = Role::query()->with('permissions:id,name')->orderBy('name')->get();

        return Inertia::render('admin/permissions/index', [
            'permissions' => $permissionNames,
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'locked' => in_array($role->name, self::LOCKED_ROLES, true),
                'permissions' => $role->permissions->pluck('name'),
            ]),
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
