<?php

namespace Tests\Feature\WordPress;

use App\Jobs\SyncWordPressUsersForSite;
use App\Models\User;
use App\Models\WordPressCredential;
use App\Models\WordPressSite;
use App\Models\WordPressUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Connect a website" is one step (site identity + credentials together) on
 * the standalone WordPress Users page — see Admin\WordPressSiteController.
 */
class WordPressSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_cannot_connect_a_site()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->post('/admin/wordpress-users/sites', [
            'name' => 'Example',
            'domain' => 'example.com',
            'wp_username' => 'admin',
            'wp_app_password' => 'secret secret secret secret',
        ])->assertForbidden();
    }

    public function test_ceo_can_connect_a_site_and_it_queues_a_sync_and_the_password_is_never_serialized_to_the_frontend()
    {
        Queue::fake();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->post('/admin/wordpress-users/sites', [
            'name' => 'Example',
            'domain' => 'example.com',
            'wp_username' => 'admin',
            'wp_app_password' => 'abcd 1234 efgh 5678',
        ])->assertRedirect();

        $site = WordPressSite::query()->where('domain', 'example.com')->firstOrFail();
        $credential = WordPressCredential::query()->where('wordpress_site_id', $site->id)->firstOrFail();
        $this->assertSame('abcd 1234 efgh 5678', $credential->wp_app_password);

        Queue::assertPushed(SyncWordPressUsersForSite::class, fn ($job) => $job->credentialId === $credential->id);

        $response = $this->actingAs($ceo)->get('/admin/wordpress-users')->assertOk();
        $row = collect($response->viewData('page')['props']['sites'])->firstWhere('id', $site->id);

        $this->assertTrue($row['credential']['wp_app_password_set']);
        $this->assertArrayNotHasKey('wp_app_password', $row['credential']);
    }

    public function test_connecting_a_site_with_a_duplicate_domain_is_rejected()
    {
        WordPressSite::factory()->create(['domain' => 'example.com']);
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->post('/admin/wordpress-users/sites', [
            'name' => 'Duplicate',
            'domain' => 'example.com',
            'wp_username' => 'admin',
            'wp_app_password' => 'secret secret secret secret',
        ])->assertSessionHasErrors('domain');
    }

    public function test_a_blank_password_on_update_preserves_the_existing_one()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $site = WordPressSite::factory()->create();
        $credential = WordPressCredential::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'original password',
        ]);

        $this->actingAs($ceo)->patch("/admin/wordpress-users/sites/{$site->id}", [
            'name' => $site->name,
            'domain' => $site->domain,
            'wp_username' => 'renamed-admin',
            'wp_app_password' => '',
        ])->assertRedirect();

        $credential->refresh();
        $this->assertSame('renamed-admin', $credential->wp_username);
        $this->assertSame('original password', $credential->wp_app_password);
    }

    public function test_disconnecting_a_site_clears_its_credential_and_cached_users()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $site = WordPressSite::factory()->create();
        $credential = WordPressCredential::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'secret',
        ]);
        WordPressUser::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_user_id' => 1,
            'username' => 'jdoe',
            'roles' => ['administrator'],
            'synced_at' => now(),
        ]);

        $this->actingAs($ceo)->delete("/admin/wordpress-users/sites/{$site->id}")->assertRedirect();

        $this->assertDatabaseMissing('wordpress_sites', ['id' => $site->id]);
        $this->assertDatabaseMissing('wordpress_credentials', ['id' => $credential->id]);
        $this->assertDatabaseMissing('wordpress_users', ['wordpress_site_id' => $site->id]);
    }

    public function test_test_connection_updates_status()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $site = WordPressSite::factory()->create(['domain' => 'https://example-site.test']);
        WordPressCredential::query()->create(['wordpress_site_id' => $site->id, 'wp_username' => 'admin', 'wp_app_password' => 'secret']);

        Http::fake(['*/wp-json/wp/v2/users*' => Http::response([], 200)]);

        $this->actingAs($ceo)->post("/admin/wordpress-users/sites/{$site->id}/test")->assertRedirect();

        $this->assertSame(WordPressCredential::STATUS_OK, WordPressCredential::query()->where('wordpress_site_id', $site->id)->firstOrFail()->status);
    }

    public function test_employees_cannot_test_sync_or_disconnect_a_site()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $site = WordPressSite::factory()->create();
        WordPressCredential::query()->create(['wordpress_site_id' => $site->id, 'wp_username' => 'admin', 'wp_app_password' => 'secret']);

        $this->actingAs($employee)->post("/admin/wordpress-users/sites/{$site->id}/test")->assertForbidden();
        $this->actingAs($employee)->post("/admin/wordpress-users/sites/{$site->id}/sync")->assertForbidden();
        $this->actingAs($employee)->delete("/admin/wordpress-users/sites/{$site->id}")->assertForbidden();
    }
}
