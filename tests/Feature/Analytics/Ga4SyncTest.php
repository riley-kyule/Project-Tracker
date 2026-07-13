<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsSyncLog;
use App\Models\Website;
use App\Models\WebsiteGa4DailyMetric;
use App\Services\Analytics\Contracts\BigQueryRunner;
use App\Services\Analytics\Ga4Sync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Ga4SyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_is_skipped_and_logged_when_bigquery_is_not_configured()
    {
        $website = Website::factory()->create(['ga4_property_id' => '123456789']);
        $sync = new Ga4Sync(new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return false;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                throw new \LogicException('should not be called');
            }
        });

        $log = $sync->syncWebsite($website, Carbon::parse('2026-07-12'));

        $this->assertSame(AnalyticsSyncLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('not configured', $log->error_message);
        $this->assertDatabaseCount('website_ga4_daily_metrics', 0);
    }

    public function test_sync_rejects_a_non_numeric_ga4_property_id()
    {
        $website = Website::factory()->create(['ga4_property_id' => 'not-a-property']);
        $sync = new Ga4Sync(new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                throw new \LogicException('should not be called');
            }
        });

        $log = $sync->syncWebsite($website, Carbon::parse('2026-07-12'));

        $this->assertSame(AnalyticsSyncLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('Invalid GA4 property id', $log->error_message);
    }

    public function test_sync_stores_daily_metrics_and_key_event_counts()
    {
        $website = Website::factory()->create(['ga4_property_id' => '123456789']);
        $sync = new Ga4Sync(new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                if (str_contains($sql, 'GROUP BY event_name')) {
                    return [
                        ['event_name' => 'WhatsApp', 'total' => 4],
                        ['event_name' => 'CallNow', 'total' => 2],
                    ];
                }

                return [['users' => 120, 'sessions' => 150, 'engaged_sessions' => 90]];
            }
        });

        $log = $sync->syncWebsite($website, Carbon::parse('2026-07-12'));

        $this->assertSame(AnalyticsSyncLog::STATUS_SUCCESS, $log->status);

        $metric = WebsiteGa4DailyMetric::query()->where('website_id', $website->id)->firstOrFail();
        $this->assertSame('2026-07-12', $metric->date->toDateString());
        $this->assertSame(120, $metric->users);
        $this->assertSame(150, $metric->sessions);
        $this->assertSame(90, $metric->engaged_sessions);
        $this->assertSame(['WhatsApp' => 4, 'CallNow' => 2], $metric->key_events);
    }

    public function test_sync_logs_failure_when_the_query_throws()
    {
        $website = Website::factory()->create(['ga4_property_id' => '123456789']);
        $sync = new Ga4Sync(new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                throw new \RuntimeException('quota exceeded');
            }
        });

        $log = $sync->syncWebsite($website, Carbon::parse('2026-07-12'));

        $this->assertSame(AnalyticsSyncLog::STATUS_FAILED, $log->status);
        $this->assertSame('quota exceeded', $log->error_message);
        $this->assertDatabaseCount('website_ga4_daily_metrics', 0);
    }

    public function test_sync_date_covers_every_website_with_a_ga4_property()
    {
        Website::factory()->create(['ga4_property_id' => '111']);
        Website::factory()->create(['ga4_property_id' => null]);

        $sync = new Ga4Sync(new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return false;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                return [];
            }
        });

        $logs = $sync->syncDate(Carbon::parse('2026-07-12'));

        $this->assertCount(1, $logs);
    }
}
