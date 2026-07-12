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
            'roles.manage',
            'boards.manage',
            'tasks.create',
            'labels.manage',
            'tickets.manage',
            'reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $rolePermissions = [
            'CEO' => ['users.view', 'departments.view', 'boards.manage', 'tasks.create', 'tickets.manage', 'reports.view'],
            'Administrator' => $permissions,
            'Department Manager' => ['users.view', 'departments.view', 'boards.manage', 'tasks.create', 'reports.view'],
            'IT Technician' => ['departments.view', 'tasks.create', 'tickets.manage'],
            'Employee' => ['departments.view', 'tasks.create'],
            'Viewer' => ['departments.view'],
        ];

        foreach ($rolePermissions as $role => $granted) {
            Role::findOrCreate($role)->syncPermissions($granted);
        }
    }
}
