<?php

namespace Tests\Feature\WordPress;

use App\Jobs\SyncWordPressUsersForSite;
use App\Models\WordPressCredential;
use App\Models\WordPressSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportWordPressSitesTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(array $rows, array $header = ['name', 'domain', 'wp_username', 'wp_app_password', 'status', 'error']): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wp-import-test-').'.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    public function test_it_connects_new_sites_and_queues_a_sync_for_each()
    {
        Queue::fake();

        $path = $this->writeCsv([
            ['Exotic Kenya', 'exotickenya.com', 'admin', 'secret pass one', 'ok', ''],
            ['Exotic Ghana', 'exoticghana.com', 'rileyseo', 'secret pass two', 'ok', ''],
        ]);

        $this->artisan('wordpress:import-sites', ['path' => $path])
            ->expectsOutputToContain('Connected: 2  Updated: 0  Skipped: 0  Failed: 0')
            ->assertSuccessful();

        $this->assertDatabaseHas('wordpress_sites', ['domain' => 'exotickenya.com']);
        $this->assertDatabaseHas('wordpress_sites', ['domain' => 'exoticghana.com']);

        $kenya = WordPressSite::query()->where('domain', 'exotickenya.com')->firstOrFail();
        $this->assertSame('secret pass one', $kenya->credential->wp_app_password);

        Queue::assertPushed(SyncWordPressUsersForSite::class, 2);

        unlink($path);
    }

    public function test_it_skips_non_ok_rows_without_touching_the_database()
    {
        $path = $this->writeCsv([
            ['Exotic Uganda', 'exoticuganda.com', '', '', 'two_factor_required', '2FA challenge detected'],
        ]);

        $this->artisan('wordpress:import-sites', ['path' => $path])
            ->expectsOutputToContain('Connected: 0  Updated: 0  Skipped: 1  Failed: 0')
            ->assertSuccessful();

        $this->assertDatabaseMissing('wordpress_sites', ['domain' => 'exoticuganda.com']);

        unlink($path);
    }

    public function test_re_running_against_an_already_connected_domain_updates_the_credential_instead_of_failing()
    {
        Queue::fake();

        $site = WordPressSite::factory()->create(['domain' => 'exotickenya.com']);
        WordPressCredential::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_username' => 'old-admin',
            'wp_app_password' => 'old password',
        ]);

        $path = $this->writeCsv([
            ['Exotic Kenya', 'exotickenya.com', 'new-admin', 'new password', 'ok', ''],
        ]);

        $this->artisan('wordpress:import-sites', ['path' => $path])
            ->expectsOutputToContain('Connected: 0  Updated: 1  Skipped: 0  Failed: 0')
            ->assertSuccessful();

        $this->assertSame(1, WordPressSite::query()->where('domain', 'exotickenya.com')->count());
        $credential = $site->credential()->firstOrFail();
        $this->assertSame('new-admin', $credential->wp_username);
        $this->assertSame('new password', $credential->wp_app_password);

        unlink($path);
    }

    public function test_a_row_missing_required_fields_is_reported_as_failed_without_aborting_the_rest()
    {
        Queue::fake();

        $path = $this->writeCsv([
            ['Broken', '', 'admin', 'secret', 'ok', ''],
            ['Exotic Kenya', 'exotickenya.com', 'admin', 'secret pass', 'ok', ''],
        ]);

        $this->artisan('wordpress:import-sites', ['path' => $path])
            ->expectsOutputToContain('Connected: 1  Updated: 0  Skipped: 0  Failed: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('wordpress_sites', ['domain' => 'exotickenya.com']);

        unlink($path);
    }
}
