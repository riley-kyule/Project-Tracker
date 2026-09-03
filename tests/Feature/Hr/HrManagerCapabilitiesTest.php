<?php

namespace Tests\Feature\Hr;

use App\Models\Board;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrManagerCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_manager_keeps_the_department_manager_permission_set(): void
    {
        $granted = Role::findByName('HR Manager')->permissions->pluck('name');

        foreach (['boards.manage', 'tasks.create', 'reports.view', 'projects.manage'] as $permission) {
            $this->assertTrue($granted->contains($permission), "HR Manager is missing {$permission}");
        }

        // …but still not final payroll sign-off.
        $this->assertFalse($granted->contains('hr.payroll.approve'));
    }

    public function test_hr_manager_can_manage_a_board_in_a_department_they_lead(): void
    {
        $user = User::factory()->create();
        $user->assignRole('HR Manager');
        $department = Department::factory()->create(['manager_id' => $user->id]);
        $user->update(['department_id' => $department->id]);
        $board = Board::factory()->create(['department_id' => $department->id, 'visibility' => 'department']);

        $this->assertTrue($user->can('update', $board));
        $this->assertTrue($user->can('view', $board));
    }
}
