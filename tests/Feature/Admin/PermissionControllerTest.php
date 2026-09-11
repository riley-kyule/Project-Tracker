<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_permissions_manage_holders_can_view_the_matrix()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $this->actingAs($employee)->get('/admin/permissions')->assertForbidden();

        $ceo = User::factory()->create()->assignRole('CEO');
        $this->actingAs($ceo)->get('/admin/permissions')->assertOk();
    }

    public function test_the_gating_permission_itself_is_never_offered_in_the_matrix()
    {
        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->get('/admin/permissions');

        $response->assertInertia(fn ($page) => $page->where('permissions', fn ($permissions) => ! collect($permissions)->contains('permissions.manage')));
    }

    public function test_ceo_can_edit_an_unlocked_roles_permissions_and_it_is_audited()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $marketing = Role::findByName('Marketing');
        $before = $marketing->permissions->pluck('name')->all();

        $response = $this->actingAs($ceo)->patch("/admin/permissions/{$marketing->id}", [
            'permissions' => ['departments.view', 'reports.view'],
        ]);

        $response->assertRedirect();
        $this->assertEqualsCanonicalizing(['departments.view', 'reports.view'], $marketing->fresh()->permissions->pluck('name')->all());

        $log = AuditLog::query()->where('event', 'role.permissions.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($marketing->id, $log->auditable_id);
        $this->assertSame($before, $log->old_values['permissions']);
        $this->assertEqualsCanonicalizing(['departments.view', 'reports.view'], $log->new_values['permissions']);
    }

    public function test_ceo_and_administrator_roles_are_locked_against_edits()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $ceoRole = Role::findByName('CEO');
        $before = $ceoRole->permissions->pluck('name')->sort()->values()->all();

        $this->actingAs($ceo)->patch("/admin/permissions/{$ceoRole->id}", ['permissions' => []])->assertForbidden();

        $this->assertSame($before, $ceoRole->fresh()->permissions->pluck('name')->sort()->values()->all());
    }

    public function test_the_gating_permission_cannot_be_granted_to_another_role_through_the_endpoint()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $marketing = Role::findByName('Marketing');

        $response = $this->actingAs($ceo)->patch("/admin/permissions/{$marketing->id}", [
            'permissions' => ['permissions.manage'],
        ]);

        $response->assertInvalid(['permissions.0']);
        $this->assertFalse($marketing->fresh()->hasPermissionTo('permissions.manage'));
    }

    public function test_a_role_without_the_gating_permission_cannot_edit_others()
    {
        $itTech = User::factory()->create()->assignRole('IT Technician');
        $marketing = Role::findByName('Marketing');

        $this->actingAs($itTech)->patch("/admin/permissions/{$marketing->id}", ['permissions' => []])->assertForbidden();
    }
}
