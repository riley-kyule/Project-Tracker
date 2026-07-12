<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_cannot_view_the_user_list()
    {
        $user = User::factory()->create()->assignRole('Employee');

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_ceo_can_view_the_user_list_but_cannot_update_users()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $target = User::factory()->create()->assignRole('Employee');

        $this->actingAs($ceo)->get('/admin/users')->assertOk();

        $this->actingAs($ceo)
            ->patch("/admin/users/{$target->id}", ['status' => 'active', 'role' => 'Employee'])
            ->assertForbidden();
    }

    public function test_administrators_can_update_role_department_and_status()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $target = User::factory()->create()->assignRole('Employee');
        $department = Department::query()->where('slug', 'it')->firstOrFail();

        $this->actingAs($admin)
            ->patch("/admin/users/{$target->id}", [
                'department_id' => $department->id,
                'job_title' => 'Support Technician',
                'status' => 'active',
                'role' => 'IT Technician',
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame($department->id, $target->department_id);
        $this->assertSame('Support Technician', $target->job_title);
        $this->assertTrue($target->hasRole('IT Technician'));
        $this->assertFalse($target->hasRole('Employee'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
            'event' => 'administrative_update',
        ]);
    }

    public function test_a_user_cannot_be_their_own_manager()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}", [
                'manager_id' => $admin->id,
                'status' => 'active',
                'role' => 'Administrator',
            ])
            ->assertSessionHasErrors('manager_id');
    }
}
