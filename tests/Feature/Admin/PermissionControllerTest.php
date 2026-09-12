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

        $response->assertInertia(fn ($page) => $page->where(
            'permissions',
            fn ($permissions) => ! collect($permissions)->pluck('name')->contains('permissions.manage'),
        ));
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

    /** Guards against a permission being added to RoleSeeder but forgotten in the plain-language catalog — it would otherwise silently show its raw slug to a non-technical viewer instead of erroring loudly here. */
    public function test_every_grantable_permission_has_a_plain_language_catalog_entry()
    {
        $ceo = User::factory()->create()->assignRole('CEO');

        $props = $this->actingAs($ceo)->get('/admin/permissions')->assertOk()->viewData('page')['props'];

        foreach ($props['permissions'] as $permission) {
            $this->assertNotSame($permission['name'], $permission['label'], "{$permission['name']} is missing a catalog entry (falls back to its raw slug).");
            $this->assertNotEmpty($permission['description'], "{$permission['name']} has no description.");
            $this->assertNotSame('Other', $permission['group'], "{$permission['name']} is missing a catalog entry (falls back to the 'Other' group).");
        }
    }

    /** Same guard, for role descriptions — a role with no entry falls back to a generic line rather than breaking the page, but every seeded role should have a real one. */
    public function test_every_role_has_a_plain_language_description()
    {
        $ceo = User::factory()->create()->assignRole('CEO');

        $props = $this->actingAs($ceo)->get('/admin/permissions')->assertOk()->viewData('page')['props'];

        foreach ($props['roles'] as $role) {
            $this->assertNotSame('A custom role — see its permissions below.', $role['description'], "{$role['name']} is missing a description.");
        }
    }

    public function test_role_cards_list_their_active_members()
    {
        $ceo = User::factory()->create(['name' => 'Ada Lovelace'])->assignRole('CEO');
        User::factory()->create()->assignRole('Employee');

        $props = $this->actingAs($ceo)->get('/admin/permissions')->assertOk()->viewData('page')['props'];

        $ceoRole = collect($props['roles'])->firstWhere('name', 'CEO');
        $this->assertTrue(collect($ceoRole['users'])->pluck('name')->contains('Ada Lovelace'));
    }
}
