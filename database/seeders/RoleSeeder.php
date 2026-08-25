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
            'Department Manager' => ['users.view', 'departments.view', 'boards.manage', 'tasks.create', 'reports.view', 'projects.manage'],
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
