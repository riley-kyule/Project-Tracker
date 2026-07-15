<?php

namespace Tests\Feature\MarketingStatistics;

use App\Models\User;
use App\Notifications\AnalyticsSourceStale;
use App\Services\Analytics\Contracts\BigQueryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class AnalyticsFreshnessAlertTest extends TestCase
{
    use RefreshDatabase;

    private function bindStaleRunner(): void
    {
        $runner = new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                // GA4 probe: no rows in the window at all → "missing".
                if (str_contains($sql, 'vw_daily_website_metrics')) {
                    return [];
                }

                // GSC freshness: 1 day behind, well within the stale threshold → "ok".
                if (str_contains($sql, 'gsc_daily_site') && str_contains($sql, 'DATE_DIFF')) {
                    return [['domain' => 'a.example.com', 'latest_date' => now()->subDay()->toDateString(), 'days_behind' => 1]];
                }

                // Ahrefs has no BigQuery table yet — every query against it fails.
                if (str_contains($sql, 'ahrefs_daily_site')) {
                    throw new RuntimeException('Not found: ahrefs_daily_site');
                }

                return [];
            }
        };

        $this->app->instance(BigQueryRunner::class, $runner);
    }

    public function test_stale_sources_notify_marketing_statistics_viewers_once()
    {
        $this->bindStaleRunner();
        Notification::fake();

        $ceo = User::factory()->create()->assignRole('CEO');
        $marketing = User::factory()->create()->assignRole('Marketing');
        $employee = User::factory()->create()->assignRole('Employee');

        $this->artisan('ewms:check-analytics-freshness')->assertSuccessful();

        Notification::assertSentTo($ceo, AnalyticsSourceStale::class, fn ($n) => $n->source === 'ga4');
        Notification::assertSentTo($marketing, AnalyticsSourceStale::class, fn ($n) => $n->source === 'ahrefs');
        Notification::assertNotSentTo($ceo, AnalyticsSourceStale::class, fn ($n) => $n->source === 'gsc');
        Notification::assertNothingSentTo($employee);
    }

    public function test_running_it_twice_within_the_dedup_window_does_not_double_notify()
    {
        $this->bindStaleRunner();

        $ceo = User::factory()->create()->assignRole('CEO');

        $this->artisan('ewms:check-analytics-freshness');
        $firstCount = $ceo->notifications()->count();

        $this->artisan('ewms:check-analytics-freshness');
        $secondCount = $ceo->notifications()->count();

        $this->assertSame($firstCount, $secondCount);
        $this->assertGreaterThan(0, $firstCount);
    }

    public function test_disabled_preference_is_respected()
    {
        $this->bindStaleRunner();
        Notification::fake();

        $ceo = User::factory()->create(['notification_preferences' => ['analytics_source_stale' => false]])->assignRole('CEO');

        $this->artisan('ewms:check-analytics-freshness');

        Notification::assertNothingSentTo($ceo);
    }
}
