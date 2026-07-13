<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/admin/departments')->assertRedirect('/login');
    }

    public function test_employees_can_view_departments_but_cannot_create()
    {
        $user = User::factory()->create()->assignRole('Employee');

        $this->actingAs($user)->get('/admin/departments')->assertOk();

        $this->actingAs($user)
            ->post('/admin/departments', ['name' => 'Skunkworks'])
            ->assertForbidden();
    }

    public function test_administrators_can_create_a_department()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)
            ->post('/admin/departments', [
                'name' => 'Skunkworks',
                'description' => 'Special projects',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['name' => 'Skunkworks', 'slug' => 'skunkworks']);
        $department = Department::query()->where('slug', 'skunkworks')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'auditable_type' => Department::class,
            'auditable_id' => $department->id,
            'event' => 'created',
        ]);
    }

    public function test_administrators_can_update_a_department()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $department = Department::query()->where('slug', 'seo')->firstOrFail();

        $this->actingAs($admin)
            ->patch("/admin/departments/{$department->id}", [
                'name' => 'Search Engine Optimization',
                'is_active' => false,
            ])
            ->assertRedirect();

        $department->refresh();
        $this->assertSame('Search Engine Optimization', $department->name);
        $this->assertFalse($department->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'auditable_type' => Department::class,
            'auditable_id' => $department->id,
            'event' => 'updated',
        ]);
    }

    public function test_duplicate_department_names_are_rejected()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)
            ->post('/admin/departments', ['name' => 'SEO'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_sub_department_cannot_itself_have_sub_departments()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/departments', ['name' => 'Local SEO', 'parent_department_id' => $seo->id])
            ->assertSessionHasErrors('parent_department_id');
    }

    public function test_a_department_with_children_cannot_become_a_child()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $it = Department::query()->where('slug', 'it')->firstOrFail();

        $this->actingAs($admin)
            ->patch("/admin/departments/{$marketing->id}", ['name' => 'Marketing', 'parent_department_id' => $it->id])
            ->assertSessionHasErrors('parent_department_id');
    }

    public function test_administrators_can_nest_a_department_under_a_parent()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/departments', ['name' => 'Email Marketing', 'parent_department_id' => $marketing->id])
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['name' => 'Email Marketing', 'parent_department_id' => $marketing->id]);
    }
}
