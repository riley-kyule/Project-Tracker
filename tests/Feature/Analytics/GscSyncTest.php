<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsSyncLog;
use App\Models\Website;
use App\Models\WebsiteGscDailyMetric;
use App\Services\Analytics\Contracts\BigQueryRunner;
use App\Services\Analytics\GscSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GscSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_is_skipped_and_logged_when_bigquery_is_not_configured()
    {
        $website = Website::factory()->create([
            'gsc_property' => 'sc-domain:example.com',
            'gsc_bigquery_dataset' => 'gsc_export',
        ]);
        $sync = new GscSync(new class implements BigQueryRunner
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

        $log = $sync->syncWebsite($website, Carbon::parse('2026-07-10'));

        $this->assertSame(AnalyticsSyncLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('not configured', $log->error_message);
        $this->assertDatabaseCount('website_gsc_daily_metrics', 0);
    }

    public function test_sync_requires_a_bigquery_dataset_to_be_set()
    {
        $website = Website::factory()->create([
            'gsc_property' => 'sc-domain:example.com',
            'gsc_bigquery_dataset' => null,
        ]);
        $sync = new GscSync(new class implements BigQueryRunner
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

        $log = $sync->syncWebsite($website, Carbon::parse('2026-07-10'));

        $this->assertSame(AnalyticsSyncLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('gsc_bigquery_dataset', $log->error_message);
    }

    public function test_sync_stores_daily_clicks_impressions_ctr_and_position()
    {
        $website = Website::factory()->create([
            'gsc_property' => 'sc-domain:example.com',
            'gsc_bigquery_dataset' => 'gsc_export',
        ]);
        $sync = new GscSync(new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                return [['clicks' => 200, 'impressions' => 5000, 'ctr' => 0.04, 'position' => 12.5]];
            }
        });

        $log = $sync->syncWebsite($website, Carbon::parse('2026-07-10'));

        $this->assertSame(AnalyticsSyncLog::STATUS_SUCCESS, $log->status);

        $metric = WebsiteGscDailyMetric::query()->where('website_id', $website->id)->firstOrFail();
        $this->assertSame('2026-07-10', $metric->date->toDateString());
        $this->assertSame(200, $metric->clicks);
        $this->assertSame(5000, $metric->impressions);
        $this->assertEqualsWithDelta(0.04, $metric->ctr, 0.0001);
        $this->assertEqualsWithDelta(12.5, $metric->position, 0.01);
    }

    public function test_sync_logs_failure_when_the_query_throws()
    {
        $website = Website::factory()->create([
            'gsc_property' => 'sc-domain:example.com',
            'gsc_bigquery_dataset' => 'gsc_export',
        ]);
        $sync = new GscSync(new class implements BigQueryRunner
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

        $log = $sync->syncWebsite($website, Carbon::parse('2026-07-10'));

        $this->assertSame(AnalyticsSyncLog::STATUS_FAILED, $log->status);
        $this->assertSame('quota exceeded', $log->error_message);
        $this->assertDatabaseCount('website_gsc_daily_metrics', 0);
    }

    public function test_sync_date_covers_every_website_with_a_gsc_property()
    {
        Website::factory()->create(['gsc_property' => 'sc-domain:example.com', 'gsc_bigquery_dataset' => 'gsc_export']);
        Website::factory()->create(['gsc_property' => null]);

        $sync = new GscSync(new class implements BigQueryRunner
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

        $logs = $sync->syncDate(Carbon::parse('2026-07-10'));

        $this->assertCount(1, $logs);
    }
}
