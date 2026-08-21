<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Services\Analytics\Contracts\BigQueryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WarmAnalyticsCacheTest extends TestCase
{
    use RefreshDatabase;

    public array $recordedCalls = [];

    /** Minimal fake: two registered sites, every report/breakdown query returns real-shaped rows. */
    private function bindFakeRunner(): void
    {
        $this->recordedCalls = [];
        $test = $this;

        $runner = new class($test) implements BigQueryRunner
        {
            public function __construct(private WarmAnalyticsCacheTest $test) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                $this->test->recordedCalls[] = compact('sql', 'parameters');

                if (str_contains($sql, 'metadata.websites')) {
                    return [
                        ['website_domain' => 'a.example.com', 'website_name' => 'Site A', 'country' => 'Kenya'],
                        ['website_domain' => 'b.example.com', 'website_name' => 'Site B', 'country' => 'Uganda'],
                    ];
                }

                if (str_contains($sql, 'vw_daily_website_metrics') && str_contains($sql, 'GROUP BY website_domain')) {
                    return [['website_domain' => 'a.example.com', 'users' => 10, 'sessions' => 20, 'engagement_rate' => 0.5]];
                }

                if (str_contains($sql, 'vw_daily_website_metrics')) {
                    return [['event_date' => $parameters['date_from'] ?? null, 'users' => 10, 'sessions' => 20, 'engaged_sessions' => 5]];
                }

                if (str_contains($sql, 'vw_key_events') && str_contains($sql, 'GROUP BY key_event')) {
                    return [['key_event' => 'purchase', 'key_event_category' => 'ecommerce', 'key_event_count' => 1, 'users' => 1]];
                }

                if (str_contains($sql, 'vw_key_events')) {
                    return [['key_events' => 1]];
                }

                if (str_contains($sql, 'vw_traffic_sources')) {
                    return [['source' => 'google', 'medium' => 'organic', 'users' => 5]];
                }

                if (str_contains($sql, 'vw_device_breakdown')) {
                    return [['device_category' => 'mobile', 'users' => 5]];
                }

                if (str_contains($sql, 'vw_landing_pages')) {
                    return [['page_location' => 'https://example.com/', 'users' => 5, 'page_views' => 6]];
                }

                if (str_contains($sql, 'vw_geo_breakdown')) {
                    return [['user_country' => 'Kenya', 'users' => 5]];
                }

                if (str_contains($sql, 'gsc_daily_site') && str_contains($sql, 'GROUP BY domain')) {
                    return [['domain' => 'a.example.com', 'clicks' => 1, 'impressions' => 10, 'average_position' => 3.0]];
                }

                if (str_contains($sql, 'gsc_daily_site')) {
                    return [['data_date' => $parameters['date_from'] ?? null, 'clicks' => 1, 'impressions' => 10, 'average_position' => 3.0]];
                }

                if (str_contains($sql, 'gsc_daily_queries')) {
                    return [['query' => 'exotic kenya', 'clicks' => 1, 'impressions' => 10, 'ctr' => 0.1, 'average_position' => 3.0]];
                }

                if (str_contains($sql, 'gsc_daily_pages')) {
                    return [['url' => 'https://example.com/', 'clicks' => 1, 'impressions' => 10, 'ctr' => 0.1]];
                }

                if (str_contains($sql, 'gsc_daily_countries')) {
                    return [['country' => 'Kenya', 'clicks' => 1, 'impressions' => 10]];
                }

                if (str_contains($sql, 'gsc_daily_devices')) {
                    return [['device' => 'mobile', 'clicks' => 1, 'impressions' => 10]];
                }

                return [];
            }
        };

        $this->app->instance(BigQueryRunner::class, $runner);
    }

    public function test_it_warms_ga4_and_gsc_reports_for_every_website_plus_the_all_sites_aggregate()
    {
        $this->bindFakeRunner();

        $this->artisan('ewms:warm-analytics-cache')->assertSuccessful();

        // ga4Report (dailyRows) + gscReport (dailyRows), per target, 3
        // targets (all sites, a.example.com, b.example.com) — 3 calls each.
        // Plus ga4Breakdowns/gscBreakdowns for 2 targets (all sites, the
        // first site) and websiteComparison's 2 grouped queries.
        $ga4TrendCalls = collect($this->recordedCalls)
            ->filter(fn ($call) => str_contains($call['sql'], 'vw_daily_website_metrics') && str_contains($call['sql'], 'GROUP BY event_date'))
            ->count();
        $gscTrendCalls = collect($this->recordedCalls)
            ->filter(fn ($call) => str_contains($call['sql'], 'gsc_daily_site') && str_contains($call['sql'], 'GROUP BY data_date'))
            ->count();
        $ga4ComparisonCalls = collect($this->recordedCalls)
            ->filter(fn ($call) => str_contains($call['sql'], 'vw_daily_website_metrics') && str_contains($call['sql'], 'GROUP BY website_domain'))
            ->count();
        $gscComparisonCalls = collect($this->recordedCalls)
            ->filter(fn ($call) => str_contains($call['sql'], 'gsc_daily_site') && str_contains($call['sql'], 'GROUP BY domain'))
            ->count();
        $ga4BreakdownCalls = collect($this->recordedCalls)->filter(fn ($call) => str_contains($call['sql'], 'vw_traffic_sources'))->count();
        $gscBreakdownCalls = collect($this->recordedCalls)->filter(fn ($call) => str_contains($call['sql'], 'gsc_daily_queries'))->count();

        $this->assertSame(3, $ga4TrendCalls);
        $this->assertSame(3, $gscTrendCalls);
        $this->assertSame(1, $ga4ComparisonCalls);
        $this->assertSame(1, $gscComparisonCalls);
        $this->assertSame(2, $ga4BreakdownCalls, 'expected GA4 breakdowns warmed for 2 targets (all sites, first site)');
        $this->assertSame(2, $gscBreakdownCalls, 'expected GSC breakdowns warmed for 2 targets (all sites, first site)');
    }

    public function test_a_marketing_statistics_page_view_after_warming_reuses_the_cache_and_makes_no_further_bigquery_calls()
    {
        $this->bindFakeRunner();
        $this->artisan('ewms:warm-analytics-cache')->assertSuccessful();

        $callsAfterWarming = count($this->recordedCalls);

        $ceo = User::factory()->create()->assignRole('CEO');
        $this->actingAs($ceo)->get('/marketing-statistics/gsc?website_id=a.example.com')->assertOk();

        $this->assertCount($callsAfterWarming, $this->recordedCalls, 'a page view matching the warmed default range should be served entirely from cache');
    }

    public function test_the_ceo_dashboards_default_traffic_view_after_warming_reuses_the_cache()
    {
        $this->bindFakeRunner();
        $this->artisan('ewms:warm-analytics-cache')->assertSuccessful();

        $callsAfterWarming = count($this->recordedCalls);

        $ceo = User::factory()->create()->assignRole('CEO');
        $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data/websites')->assertOk();
        $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data?'.http_build_query([
            'website_domain' => 'all',
            'date_from' => now()->subDays(30)->startOfDay()->toDateString(),
            'date_to' => now()->subDay()->startOfDay()->toDateString(),
        ]))->assertOk();
        $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data/breakdowns?'.http_build_query([
            'website_domain' => 'all',
            'date_from' => now()->subDays(30)->startOfDay()->toDateString(),
            'date_to' => now()->subDay()->startOfDay()->toDateString(),
        ]))->assertOk();

        $this->assertCount(
            $callsAfterWarming, $this->recordedCalls,
            'the CEO dashboard\'s "All Platforms"/last-30-days default (report + breakdowns) should be served entirely from the warmed cache',
        );
    }

    public function test_it_skips_gracefully_when_the_registry_itself_is_unavailable()
    {
        $runner = new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                throw new RuntimeException('BigQuery is down');
            }
        };
        $this->app->instance(BigQueryRunner::class, $runner);

        $this->artisan('ewms:warm-analytics-cache')->assertSuccessful();
    }
}
