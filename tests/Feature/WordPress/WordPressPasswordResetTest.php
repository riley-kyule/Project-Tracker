<?php

namespace Tests\Feature\WordPress;

use App\Jobs\ResetAllWordPressStaffPasswords;
use App\Models\User;
use App\Models\WordPressCredential;
use App\Models\WordPressPasswordReset;
use App\Models\WordPressSite;
use App\Models\WordPressUser;
use App\Services\WordPress\WordPressUserBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WordPressPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_cannot_trigger_or_view_a_bulk_reset()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->postJson('/admin/wordpress-users/reset-all-passwords')->assertForbidden();
        $this->actingAs($employee)->getJson('/admin/wordpress-users/reset-all-passwords/latest')->assertForbidden();
    }

    public function test_ceo_can_trigger_a_bulk_reset()
    {
        Queue::fake();
        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->postJson('/admin/wordpress-users/reset-all-passwords')->assertCreated();

        $reset = WordPressPasswordReset::query()->firstOrFail();
        $response->assertJsonPath('reset.id', $reset->id);
        $this->assertSame(WordPressPasswordReset::STATUS_PENDING, $reset->status);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $ceo->id,
            'auditable_type' => WordPressPasswordReset::class,
            'auditable_id' => $reset->id,
            'event' => 'wp_bulk_password_reset_started',
        ]);

        Queue::assertPushed(ResetAllWordPressStaffPasswords::class, fn (ResetAllWordPressStaffPasswords $job) => $job->resetId === $reset->id);
    }

    public function test_a_second_bulk_reset_cannot_start_while_one_is_in_progress()
    {
        Queue::fake();
        $admin = User::factory()->create()->assignRole('Administrator');
        WordPressPasswordReset::create(['actor_id' => $admin->id, 'status' => WordPressPasswordReset::STATUS_RUNNING]);

        $this->actingAs($admin)->postJson('/admin/wordpress-users/reset-all-passwords')->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_the_job_resets_every_synced_user_across_every_site_and_reports_progress()
    {
        $siteA = WordPressSite::factory()->create();
        $siteB = WordPressSite::factory()->create();
        WordPressCredential::query()->create(['wordpress_site_id' => $siteA->id, 'wp_username' => 'admin', 'wp_app_password' => 'secret']);
        WordPressCredential::query()->create(['wordpress_site_id' => $siteB->id, 'wp_username' => 'admin', 'wp_app_password' => 'secret']);
        $userA = WordPressUser::query()->create(['wordpress_site_id' => $siteA->id, 'wp_user_id' => 1, 'username' => 'alice', 'roles' => ['editor'], 'synced_at' => now()]);
        $userB = WordPressUser::query()->create(['wordpress_site_id' => $siteB->id, 'wp_user_id' => 2, 'username' => 'bob', 'roles' => ['editor'], 'synced_at' => now()]);

        Http::fake([
            "*/wp-json/wp/v2/users/{$userA->wp_user_id}" => Http::response(['id' => $userA->wp_user_id], 200),
            "*/wp-json/wp/v2/users/{$userB->wp_user_id}" => Http::response(['id' => $userB->wp_user_id], 200),
        ]);

        $admin = User::factory()->create()->assignRole('Administrator');
        $reset = WordPressPasswordReset::create(['actor_id' => $admin->id, 'status' => WordPressPasswordReset::STATUS_PENDING]);

        (new ResetAllWordPressStaffPasswords($reset->id))->handle(app(WordPressUserBulkAction::class));

        $reset->refresh();
        $this->assertSame(WordPressPasswordReset::STATUS_SUCCEEDED, $reset->status);
        $this->assertSame(2, $reset->total);
        $this->assertSame(2, $reset->processed);
        $this->assertSame(2, $reset->succeeded);
        $this->assertSame(0, $reset->failed);
        $this->assertEmpty($reset->failures);
        $this->assertNotNull($reset->started_at);
        $this->assertNotNull($reset->finished_at);

        $results = Cache::get($reset->resultsCacheKey());
        $this->assertCount(2, $results);
        $this->assertNotEmpty(collect($results)->firstWhere('id', $userA->id)['password']);
        $this->assertNotEmpty(collect($results)->firstWhere('id', $userB->id)['password']);

        // Every underlying per-user reset is still individually audited.
        $this->assertDatabaseHas('audit_logs', ['event' => 'wp_user_password_reset', 'auditable_id' => $userA->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'wp_user_password_reset', 'auditable_id' => $userB->id]);
    }

    public function test_the_job_records_partial_failure_without_a_password_for_the_failed_row()
    {
        $site = WordPressSite::factory()->create();
        WordPressCredential::query()->create(['wordpress_site_id' => $site->id, 'wp_username' => 'admin', 'wp_app_password' => 'secret']);
        $wpUser = WordPressUser::query()->create(['wordpress_site_id' => $site->id, 'wp_user_id' => 9, 'username' => 'carol', 'roles' => ['editor'], 'synced_at' => now()]);

        Http::fake(["*/wp-json/wp/v2/users/{$wpUser->wp_user_id}" => Http::response(['message' => 'Unauthorized'], 401)]);

        $admin = User::factory()->create()->assignRole('Administrator');
        $reset = WordPressPasswordReset::create(['actor_id' => $admin->id, 'status' => WordPressPasswordReset::STATUS_PENDING]);

        (new ResetAllWordPressStaffPasswords($reset->id))->handle(app(WordPressUserBulkAction::class));

        $reset->refresh();
        $this->assertSame(WordPressPasswordReset::STATUS_SUCCEEDED, $reset->status); // the run itself completed; this row within it failed
        $this->assertSame(1, $reset->failed);
        $this->assertSame('carol', $reset->failures[0]['username']);
        $this->assertArrayNotHasKey('password', $reset->failures[0]);

        $results = Cache::get($reset->resultsCacheKey());
        $this->assertArrayNotHasKey('password', collect($results)->firstWhere('id', $wpUser->id));
    }

    public function test_the_job_no_ops_when_the_reset_row_was_deleted_before_it_ran()
    {
        Http::fake();

        (new ResetAllWordPressStaffPasswords(999999))->handle(app(WordPressUserBulkAction::class));

        Http::assertNothingSent();
    }

    public function test_results_endpoint_returns_the_passwords_once_and_acknowledge_clears_them()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $reset = WordPressPasswordReset::create([
            'actor_id' => $admin->id,
            'status' => WordPressPasswordReset::STATUS_SUCCEEDED,
            'total' => 1,
            'processed' => 1,
            'succeeded' => 1,
        ]);
        Cache::put($reset->resultsCacheKey(), [['id' => 1, 'username' => 'alice', 'site' => 'Site A', 'status' => 'ok', 'password' => 'Sup3r$ecret!']], now()->addMinutes(30));

        $response = $this->actingAs($admin)->getJson("/admin/wordpress-users/reset-all-passwords/{$reset->id}/results")->assertOk();
        $response->assertJsonPath('expired', false);
        $response->assertJsonPath('results.0.password', 'Sup3r$ecret!');

        $this->actingAs($admin)->deleteJson("/admin/wordpress-users/reset-all-passwords/{$reset->id}/results")->assertOk();

        $again = $this->actingAs($admin)->getJson("/admin/wordpress-users/reset-all-passwords/{$reset->id}/results")->assertOk();
        $again->assertJsonPath('expired', true);
        $again->assertJsonPath('results', null);
    }

    public function test_results_are_not_available_until_the_reset_has_succeeded()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $reset = WordPressPasswordReset::create(['actor_id' => $admin->id, 'status' => WordPressPasswordReset::STATUS_RUNNING]);

        $this->actingAs($admin)->getJson("/admin/wordpress-users/reset-all-passwords/{$reset->id}/results")->assertStatus(409);
    }
}
