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

    public function test_administrator_can_create_a_user_and_receives_a_generated_password()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $department = Department::query()->where('slug', 'it')->firstOrFail();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Hire',
            'email' => 'new.hire@example.com',
            'department_id' => $department->id,
            'job_title' => 'Support Technician',
            'status' => 'active',
            'role' => 'IT Technician',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('generated_password');

        $user = User::query()->where('email', 'new.hire@example.com')->firstOrFail();
        $this->assertSame($department->id, $user->department_id);
        $this->assertTrue($user->hasRole('IT Technician'));
        $this->assertNotNull($user->password);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'event' => 'created',
        ]);
    }

    public function test_generated_password_actually_authenticates()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Hire',
            'email' => 'new.hire@example.com',
            'status' => 'active',
            'role' => 'Employee',
        ]);

        $flashed = session('generated_password');
        [$email, $password] = explode(' — ', $flashed);

        $this->post('/logout');
        $this->post('/login', ['email' => $email, 'password' => $password])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_employees_cannot_create_users()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)
            ->post('/admin/users', ['name' => 'Nope', 'email' => 'nope@example.com', 'status' => 'active', 'role' => 'Employee'])
            ->assertForbidden();
    }

    public function test_email_must_be_unique()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $existing = User::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/users', ['name' => 'Dup', 'email' => $existing->email, 'status' => 'active', 'role' => 'Employee'])
            ->assertSessionHasErrors('email');
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
