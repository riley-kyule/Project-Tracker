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

        $permissions = [...$permissions, ...$hrPermissions];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $rolePermissions = [
            // CEO holds every permission Administrator holds — an explicit product
            // decision reversing the prior separation-of-duties split (see
            // PERMISSIONS_MATRIX.md). Administrator remains a distinct role for
            // operational/on-call purposes but no longer differs from CEO in
            // capability, so both point at the same shared array on purpose:
            // it makes that parity impossible to accidentally drift apart again.
            'CEO' => $permissions,
            'Administrator' => $permissions,
            // Full HR access including compensation and payroll processing;
            // final payroll sign-off (hr.payroll.approve) stays with CEO/Admin.
            'HR Manager' => [...array_diff($hrPermissions, ['hr.payroll.approve']), 'users.view', 'departments.view'],
            // People, leave and assets — but no salary or payroll visibility.
            'HR Staff' => [...$hrStaffPermissions, 'users.view', 'departments.view'],
            // Managers approve their team's leave and see (non-salary) employee
            // records for their reports; scoping lives in the policies.
            'Department Manager' => ['users.view', 'departments.view', 'boards.manage', 'tasks.create', 'reports.view', 'projects.manage', 'hr.employees.view', 'hr.leave.view', 'hr.leave.approve'],
            'IT Technician' => ['departments.view', 'tasks.create', 'tickets.manage'],
            'Marketing' => ['departments.view', 'tasks.create', 'view marketing statistics'],
            'Customer Service' => ['departments.view', 'tasks.create'],
            'Employee' => ['departments.view', 'tasks.create'],
            'Viewer' => ['departments.view'],
        ];

        foreach ($rolePermissions as $role => $granted) {
            Role::findOrCreate($role)->syncPermissions($granted);
        }
    }
}
