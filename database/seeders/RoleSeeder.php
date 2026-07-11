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
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $rolePermissions = [
            'CEO' => ['users.view', 'departments.view'],
            'Administrator' => $permissions,
            'Department Manager' => ['users.view', 'departments.view'],
            'IT Technician' => ['departments.view'],
            'Employee' => ['departments.view'],
            'Viewer' => ['departments.view'],
        ];

        foreach ($rolePermissions as $role => $granted) {
            Role::findOrCreate($role)->syncPermissions($granted);
        }
    }
}
