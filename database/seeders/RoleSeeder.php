<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Base permission set for Milestone 1; feature milestones add their own.
     * Role capabilities follow PERMISSIONS_MATRIX.md.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.manage',
            'departments.view',
            'departments.manage',
            'boards.manage',
            'tasks.create',
            'labels.manage',
            'tickets.manage',
            'reports.view',
            'projects.manage',
            'registry.manage',
            'system.deploy',
            'view marketing statistics',
            'wordpress.manage',
            // Gates the role↔permission matrix at /admin/permissions — deliberately
            // left out of every curated role array below (Department Manager, HR
            // Manager, ...) so it only reaches whoever CEO/Administrator's shared
            // $permissions array reaches, never something grantable to itself
            // through the very UI it gates.
            'permissions.manage',
            // Gates issuing MCP tokens (/admin/mcp) — unlike permissions.manage,
            // this one is safe to delegate later through that same matrix, so
            // it's ordinary: reaches CEO/Administrator by default, but PermissionController
            // will let it be granted to another role same as any other permission.
            'mcp.manage',
        ];

        // SEO Board (SEO Board Requirements Specification v1.1 §9). "Approve"
        // is the HOD action (approve/correct/reject/exempt items, approve the
        // weekly plan); "manage templates" and "manage settings" are separate
        // so a department head can run day-to-day approvals without also
        // being able to redefine the point library or notification recipients.
        $seoPermissions = [
            'seo.cards.view',
            'seo.cards.update',
            'seo.cards.approve',
            'seo.cards.reopen',
            'seo.templates.manage',
            'seo.settings.manage',
        ];

        // Customer Service Board (Customer Service Board Requirements
        // Specification v1.0 §10). Same split as the SEO permissions above:
        // "approve" is the HOD action (approve/correct/reject/exempt items,
        // approve the weekly plan, clear/flag sales records, set weekly
        // targets); templates and settings stay separate so a department
        // head can run day-to-day approvals without redefining the point
        // library or notification recipients.
        $csPermissions = [
            'cs.cards.view',
            'cs.cards.update',
            'cs.cards.approve',
            'cs.cards.reopen',
            'cs.templates.manage',
            'cs.settings.manage',
        ];

        // HR module. Compensation and payroll are deliberately split out from
        // the rest of HR so an "HR Staff" role can administer people, leave and
        // assets without ever seeing salary figures or running payroll.
        $hrPermissions = [
            'hr.employees.view',
            'hr.employees.manage',
            'hr.compensation.view',
            'hr.compensation.manage',
            'hr.assets.view',
            'hr.assets.manage',
            'hr.leave.view',
            'hr.leave.manage',
            'hr.leave.approve',
            'hr.payroll.view',
            'hr.payroll.process',
            'hr.payroll.approve',
            'hr.performance.view',
            'hr.performance.manage',
        ];

        $hrStaffPermissions = array_values(array_diff($hrPermissions, [
            'hr.compensation.view',
            'hr.compensation.manage',
            'hr.payroll.view',
            'hr.payroll.process',
            'hr.payroll.approve',
        ]));

        $permissions = [...$permissions, ...$hrPermissions, ...$seoPermissions, ...$csPermissions];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // An HR Manager is almost always also a department head, so the role
        // carries the Department Manager capabilities (board/task/project
        // management for departments they lead) on top of the HR module.
        $departmentManager = [
            'users.view', 'departments.view', 'boards.manage', 'tasks.create',
            'reports.view', 'projects.manage', 'hr.employees.view', 'hr.leave.view', 'hr.leave.approve',
        ];

        $rolePermissions = [
            // CEO holds every permission Administrator holds — an explicit product
            // decision reversing the prior separation-of-duties split (see
            // PERMISSIONS_MATRIX.md). Administrator remains a distinct role for
            // operational/on-call purposes but no longer differs from CEO in
            // capability, so both point at the same shared array on purpose:
            // it makes that parity impossible to accidentally drift apart again.
            'CEO' => $permissions,
            'Administrator' => $permissions,
            // Full HR access including compensation and payroll processing,
            // plus Department Manager capabilities; final payroll sign-off
            // (hr.payroll.approve) stays with CEO/Admin.
            'HR Manager' => array_values(array_unique([
                ...$departmentManager,
                ...array_diff($hrPermissions, ['hr.payroll.approve']),
            ])),
            // People, leave and assets — but no salary or payroll visibility.
            'HR Staff' => [...$hrStaffPermissions, 'users.view', 'departments.view'],
            // Managers approve their team's leave and see (non-salary) employee
            // records for their reports; scoping lives in the policies. Also
            // covers the SEO Board's HOD role (§9): maintains the template
            // library and approves cards for whichever department(s) they
            // lead — SeoDailyCardPolicy/SeoWeeklyCardPolicy/SeoTaskTemplatePolicy
            // scope that to their own department, this permission alone
            // doesn't reach every department's cards.
            'Department Manager' => [
                ...$departmentManager,
                'seo.cards.view', 'seo.cards.approve', 'seo.cards.reopen', 'seo.templates.manage',
                'cs.cards.view', 'cs.cards.approve', 'cs.cards.reopen', 'cs.templates.manage',
            ],
            'IT Technician' => ['departments.view', 'tasks.create', 'tickets.manage'],
            // Services the R&D ticket queue the same way IT Technician services
            // IT's — see Ticket::TEAM_DEPARTMENT_SLUGS. Routing is by department
            // membership, not this role, but anyone actually doing the work
            // needs tickets.manage to act on what lands in that queue.
            'Research & Development' => ['departments.view', 'tasks.create', 'tickets.manage'],
            // Same reasoning as HR Manager: the head of R&D is a department
            // head first (Department Manager capabilities — boards, leave
            // approval, reports) and also needs to see/work their own team's
            // ticket queue, which the plain Research & Development role alone
            // doesn't grant.
            'R&D Manager' => array_values(array_unique([...$departmentManager, 'tickets.manage'])),
            // SEO Board execution permissions: any SEO employee's own daily/weekly
            // cards, scoped by SeoDailyCardPolicy/SeoWeeklyCardPolicy to records
            // where employees.user_id matches them — not every card company-wide.
            'Marketing' => ['departments.view', 'tasks.create', 'view marketing statistics', 'seo.cards.view', 'seo.cards.update'],
            // Customer Service Board execution permissions: any Customer
            // Service employee's own daily/weekly cards and sales records,
            // scoped by CsDailyCardPolicy/CsWeeklyCardPolicy/CsSalesRecordPolicy
            // to records where employees.user_id matches them.
            'Customer Service' => ['departments.view', 'tasks.create', 'cs.cards.view', 'cs.cards.update'],
            // seo.cards.view/update stay here deliberately, same as
            // seo.cards.approve stays on the blanket "Department Manager"
            // role above: the permission is only the coarse "this role may
            // call these routes at all" gate, never the actual scope. Until
            // 2026-09-23 that was the only gate SeoBoardController::mine()
            // had, so any employee — in any department — got a daily card
            // silently provisioned under their own department the moment
            // they visited the SEO Board, and that department's HOD ended up
            // on the midnight report's recipient list for work that was
            // never actually SEO work. The real fix is
            // User::isSeoEmployee(): mine() now refuses to provision
            // anything unless the visitor's own department resolves to SEO,
            // regardless of which role granted them this permission — so an
            // item-status update on a card an employee already, legitimately
            // owns (SeoDailyCardPolicy::update()'s ownership check) still
            // needs this permission, but no non-SEO employee can ever end up
            // owning a card to begin with.
            'Employee' => ['departments.view', 'tasks.create', 'seo.cards.view', 'seo.cards.update'],
            'Viewer' => ['departments.view'],
        ];

        foreach ($rolePermissions as $role => $granted) {
            Role::findOrCreate($role)->syncPermissions($granted);
        }
    }
}
