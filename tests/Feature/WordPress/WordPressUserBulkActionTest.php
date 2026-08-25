<?php

namespace Tests\Feature\WordPress;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteWordPressCredential;
use App\Models\WordPressUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressUserBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private function credentialWithUser(): array
    {
        $website = Website::factory()->create(['domain' => 'https://example-site.test']);
        $credential = WebsiteWordPressCredential::query()->create([
            'website_id' => $website->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'secret',
        ]);
        $wpUser = WordPressUser::query()->create([
            'website_id' => $website->id,
            'wp_user_id' => 5,
            'username' => 'jdoe',
            'email' => 'jdoe@exotic-online.com',
            'roles' => ['editor'],
            'synced_at' => now(),
        ]);

        return [$website, $credential, $wpUser];
    }

    public function test_employees_cannot_perform_bulk_actions()
    {
        [, , $wpUser] = $this->credentialWithUser();
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)
            ->post('/admin/wordpress-users/bulk-change-role', ['wordpress_user_ids' => [$wpUser->id], 'roles' => ['administrator']])
            ->assertForbidden();
    }

    public function test_add_creates_the_user_on_every_selected_site_and_logs_audit_entries()
    {
        $website = Website::factory()->create();
        WebsiteWordPressCredential::query()->create(['website_id' => $website->id, 'wp_username' => 'admin', 'wp_app_password' => 'secret']);
        $ceo = User::factory()->create()->assignRole('CEO');

        Http::fake(['*/wp-json/wp/v2/users' => Http::response(['id' => 42], 201)]);

        $this->actingAs($ceo)->post('/admin/wordpress-users/bulk-add', [
            'website_ids' => [$website->id],
            'username' => 'newstaff',
            'email' => 'newstaff@exotic-online.com',
            'password' => 'a-long-enough-password',
            'roles' => ['editor'],
        ])->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/wp-json/wp/v2/users'));
        $this->assertDatabaseHas('wordpress_users', ['website_id' => $website->id, 'wp_user_id' => 42, 'username' => 'newstaff']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'wp_user_created', 'actor_id' => $ceo->id]);
    }

    public function test_change_role_updates_the_remote_site_and_the_local_cache()
    {
        [, , $wpUser] = $this->credentialWithUser();
        $ceo = User::factory()->create()->assignRole('CEO');

        Http::fake(["*/wp-json/wp/v2/users/{$wpUser->wp_user_id}" => Http::response(['id' => $wpUser->wp_user_id], 200)]);

        $this->actingAs($ceo)->post('/admin/wordpress-users/bulk-change-role', [
            'wordpress_user_ids' => [$wpUser->id],
            'roles' => ['administrator'],
        ])->assertRedirect();

        $this->assertSame(['administrator'], $wpUser->fresh()->roles);
        $this->assertDatabaseHas('audit_logs', ['event' => 'wp_user_role_changed', 'auditable_id' => $wpUser->id]);
    }

    public function test_update_email_accepts_a_distinct_email_per_selected_user()
    {
        [, , $wpUser] = $this->credentialWithUser();
        $ceo = User::factory()->create()->assignRole('CEO');

        Http::fake(["*/wp-json/wp/v2/users/{$wpUser->wp_user_id}" => Http::response(['id' => $wpUser->wp_user_id], 200)]);

        $this->actingAs($ceo)->post('/admin/wordpress-users/bulk-update-email', [
            'updates' => [['id' => $wpUser->id, 'email' => 'new-email@exotic-online.com']],
        ])->assertRedirect();

        $this->assertSame('new-email@exotic-online.com', $wpUser->fresh()->email);
    }

    public function test_delete_removes_the_user_remotely_and_locally()
    {
        [, , $wpUser] = $this->credentialWithUser();
        $ceo = User::factory()->create()->assignRole('CEO');

        Http::fake(["*/wp-json/wp/v2/users/{$wpUser->wp_user_id}*" => Http::response(['deleted' => true], 200)]);

        $this->actingAs($ceo)->delete('/admin/wordpress-users/bulk-delete', ['wordpress_user_ids' => [$wpUser->id]])->assertRedirect();

        $this->assertDatabaseMissing('wordpress_users', ['id' => $wpUser->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'wp_user_deleted']);
    }

    public function test_a_partial_failure_across_sites_is_reported_without_aborting_the_rest()
    {
        $goodWebsite = Website::factory()->create();
        $badWebsite = Website::factory()->create();
        WebsiteWordPressCredential::query()->create(['website_id' => $goodWebsite->id, 'wp_username' => 'admin', 'wp_app_password' => 'secret']);
        WebsiteWordPressCredential::query()->create(['website_id' => $badWebsite->id, 'wp_username' => 'admin', 'wp_app_password' => 'wrong']);
        $goodUser = WordPressUser::query()->create(['website_id' => $goodWebsite->id, 'wp_user_id' => 1, 'username' => 'good', 'roles' => ['editor'], 'synced_at' => now()]);
        $badUser = WordPressUser::query()->create(['website_id' => $badWebsite->id, 'wp_user_id' => 2, 'username' => 'bad', 'roles' => ['editor'], 'synced_at' => now()]);
        $ceo = User::factory()->create()->assignRole('CEO');

        Http::fake([
            "*/wp-json/wp/v2/users/{$goodUser->wp_user_id}" => Http::response(['id' => 1], 200),
            "*/wp-json/wp/v2/users/{$badUser->wp_user_id}" => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $response = $this->actingAs($ceo)->post('/admin/wordpress-users/bulk-change-role', [
            'wordpress_user_ids' => [$goodUser->id, $badUser->id],
            'roles' => ['administrator'],
        ]);

        $response->assertRedirect();
        $this->assertSame(['administrator'], $goodUser->fresh()->roles);
        $this->assertSame(['editor'], $badUser->fresh()->roles);
    }
}
