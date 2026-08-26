<?php

namespace Tests\Feature\WordPress;

use App\Jobs\SyncWordPressUsersForSite;
use App\Models\WordPressCredential;
use App\Models\WordPressSite;
use App\Models\WordPressUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncWordPressUsersForSiteTest extends TestCase
{
    use RefreshDatabase;

    private function credential(): WordPressCredential
    {
        $site = WordPressSite::factory()->create(['domain' => 'https://example-site.test']);

        return WordPressCredential::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_username' => 'admin',
            'wp_app_password' => 'secret',
        ]);
    }

    public function test_a_successful_sync_persists_users_and_marks_the_credential_ok()
    {
        $credential = $this->credential();

        Http::fake([
            '*/wp-json/wp/v2/users*' => Http::response([
                ['id' => 1, 'username' => 'jdoe', 'email' => 'jdoe@exotic-online.com', 'name' => 'Jane Doe', 'roles' => ['administrator']],
                ['id' => 2, 'username' => 'bsmith', 'email' => 'bsmith@example.com', 'name' => 'Bob Smith', 'roles' => ['editor']],
            ]),
        ]);

        SyncWordPressUsersForSite::dispatchSync($credential->id);

        $this->assertSame(2, WordPressUser::query()->where('wordpress_site_id', $credential->wordpress_site_id)->count());
        $this->assertSame(WordPressCredential::STATUS_OK, $credential->fresh()->status);
        $this->assertNull($credential->fresh()->last_error);
    }

    public function test_a_failed_response_marks_the_credential_as_error_without_throwing()
    {
        $credential = $this->credential();

        Http::fake([
            '*/wp-json/wp/v2/users*' => Http::response(['message' => 'Sorry, you are not allowed to do that.'], 401),
        ]);

        SyncWordPressUsersForSite::dispatchSync($credential->id);

        $credential->refresh();
        $this->assertSame(WordPressCredential::STATUS_ERROR, $credential->status);
        $this->assertSame('Sorry, you are not allowed to do that.', $credential->last_error);
        $this->assertSame(0, WordPressUser::query()->where('wordpress_site_id', $credential->wordpress_site_id)->count());
    }

    public function test_a_user_missing_from_a_re_sync_is_removed_locally()
    {
        $credential = $this->credential();
        WordPressUser::query()->create([
            'wordpress_site_id' => $credential->wordpress_site_id,
            'wp_user_id' => 99,
            'username' => 'stale-user',
            'roles' => ['subscriber'],
            'synced_at' => now()->subDay(),
        ]);

        Http::fake([
            '*/wp-json/wp/v2/users*' => Http::response([
                ['id' => 1, 'username' => 'jdoe', 'email' => 'jdoe@exotic-online.com', 'name' => 'Jane Doe', 'roles' => ['administrator']],
            ]),
        ]);

        SyncWordPressUsersForSite::dispatchSync($credential->id);

        $this->assertDatabaseMissing('wordpress_users', ['wordpress_site_id' => $credential->wordpress_site_id, 'wp_user_id' => 99]);
        $this->assertDatabaseHas('wordpress_users', ['wordpress_site_id' => $credential->wordpress_site_id, 'wp_user_id' => 1]);
    }

    public function test_a_deleted_credential_is_a_no_op()
    {
        Http::fake();

        // Should not throw even though the credential id resolves to nothing.
        SyncWordPressUsersForSite::dispatchSync(999999);

        Http::assertNothingSent();
        $this->assertTrue(true);
    }
}
