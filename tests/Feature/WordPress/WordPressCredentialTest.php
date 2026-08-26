<?php

namespace Tests\Feature\WordPress;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteWordPressCredential;
use App\Models\WordPressUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WordPress credentials are created/edited from the Websites admin page
 * itself (Admin\WebsiteController), not a separate Settings screen — see
 * WebsiteController::syncWordPressCredential(). Test/sync/destroy remain
 * dedicated actions (Admin\WebsiteWordPressCredentialController).
 */
class WordPressCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_cannot_add_wordpress_credentials()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $website = Website::factory()->create(['domain' => 'example.com']);

        $this->actingAs($employee)->patch("/admin/websites/{$website->id}", [
            'name' => $website->name,
            'status' => 'active',
            'wp_username' => 'admin',
            'wp_app_password' => 'secret secret secret secret',
        ])->assertForbidden();
    }

    public function test_a_registry_manager_without_wordpress_manage_cannot_add_credentials_either()
    {
        // registry.manage and wordpress.manage are independently gated even though
        // CEO/Administrator hold both today — a future role with just one shouldn't
        // silently gain the other.
        $user = User::factory()->create();
        $user->givePermissionTo('registry.manage');
        $website = Website::factory()->create(['domain' => 'example.com']);

        $this->actingAs($user)->patch("/admin/websites/{$website->id}", [
            'name' => $website->name,
            'status' => 'active',
            'wp_username' => 'admin',
            'wp_app_password' => 'secret secret secret secret',
        ])->assertForbidden();
    }

    public function test_ceo_can_add_credentials_from_the_website_form_and_the_password_is_never_serialized_to_the_frontend()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $website = Website::factory()->create(['domain' => 'example.com']);

        $this->actingAs($ceo)->patch("/admin/websites/{$website->id}", [
            'name' => $website->name,
            'status' => 'active',
            'wp_username' => 'admin',
            'wp_app_password' => 'abcd 1234 efgh 5678',
        ])->assertRedirect();

        $credential = WebsiteWordPressCredential::query()->where('website_id', $website->id)->firstOrFail();
        $this->assertSame('abcd 1234 efgh 5678', $credential->wp_app_password);

        $response = $this->actingAs($ceo)->get('/admin/websites')->assertOk();
        $row = collect($response->viewData('page')['props']['websites'])->firstWhere('id', $website->id);

        $this->assertTrue($row['wordpress_credential']['wp_app_password_set']);
        $this->assertArrayNotHasKey('wp_app_password', $row['wordpress_credential']);
    }

    public function test_adding_credentials_without_a_domain_is_rejected()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $website = Website::factory()->create(['domain' => null]);

        $this->actingAs($ceo)->patch("/admin/websites/{$website->id}", [
            'name' => $website->name,
            'status' => 'active',
            'wp_username' => 'admin',
            'wp_app_password' => 'secret secret secret secret',
        ])->assertSessionHasErrors('wp_username');

        $this->assertDatabaseMissing('website_wordpress_credentials', ['website_id' => $website->id]);
    }

    public function test_a_blank_password_on_update_preserves_the_existing_one()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $website = Website::factory()->create(['domain' => 'example.com']);
        $credential = WebsiteWordPressCredential::query()->create([
            'website_id' => $website->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'original password',
        ]);

        $this->actingAs($ceo)->patch("/admin/websites/{$website->id}", [
            'name' => $website->name,
            'status' => 'active',
            'wp_username' => 'renamed-admin',
            'wp_app_password' => '',
        ])->assertRedirect();

        $credential->refresh();
        $this->assertSame('renamed-admin', $credential->wp_username);
        $this->assertSame('original password', $credential->wp_app_password);
    }

    public function test_saving_a_website_without_touching_wordpress_fields_leaves_no_credential_untouched()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $website = Website::factory()->create(['domain' => 'example.com']);
        $credential = WebsiteWordPressCredential::query()->create([
            'website_id' => $website->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'original password',
        ]);

        $this->actingAs($ceo)->patch("/admin/websites/{$website->id}", [
            'name' => 'Renamed site',
            'status' => 'active',
            'wp_username' => 'admin',
            'wp_app_password' => '',
        ])->assertRedirect();

        $this->assertSame('original password', $credential->fresh()->wp_app_password);
        $this->assertSame('Renamed site', $website->fresh()->name);
    }

    public function test_deleting_a_credential_also_clears_its_cached_wordpress_users()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $website = Website::factory()->create(['domain' => 'example.com']);
        $credential = WebsiteWordPressCredential::query()->create([
            'website_id' => $website->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'secret',
        ]);
        WordPressUser::query()->create([
            'website_id' => $website->id,
            'wp_user_id' => 1,
            'username' => 'jdoe',
            'roles' => ['administrator'],
            'synced_at' => now(),
        ]);

        $this->actingAs($ceo)->delete("/admin/websites/{$website->id}/wordpress-credential")->assertRedirect();

        $this->assertDatabaseMissing('website_wordpress_credentials', ['id' => $credential->id]);
        $this->assertDatabaseMissing('wordpress_users', ['website_id' => $website->id]);
    }

    public function test_employees_cannot_test_sync_or_delete_credentials()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $website = Website::factory()->create(['domain' => 'example.com']);
        WebsiteWordPressCredential::query()->create(['website_id' => $website->id, 'wp_username' => 'admin', 'wp_app_password' => 'secret']);

        $this->actingAs($employee)->post("/admin/websites/{$website->id}/wordpress-credential/test")->assertForbidden();
        $this->actingAs($employee)->post("/admin/websites/{$website->id}/wordpress-credential/sync")->assertForbidden();
        $this->actingAs($employee)->delete("/admin/websites/{$website->id}/wordpress-credential")->assertForbidden();
    }
}
