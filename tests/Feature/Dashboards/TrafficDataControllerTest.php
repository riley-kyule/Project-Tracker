<?php

namespace Tests\Feature\Dashboards;

use App\Models\User;
use App\Services\Analytics\Contracts\BigQueryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TrafficDataControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Public so the anonymous fake below (a different class) can append to it. */
    public array $recordedCalls = [];

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/dashboards/ceo/traffic-data/websites')->assertRedirect('/login');
        $this->get('/dashboards/ceo/traffic-data')->assertRedirect('/login');
        $this->get('/dashboards/ceo/traffic-data/breakdowns')->assertRedirect('/login');
    }

    public function test_employees_cannot_view_traffic_data()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/dashboards/ceo/traffic-data/websites')->assertForbidden();
        $this->actingAs($employee)->get('/dashboards/ceo/traffic-data')->assertForbidden();
        $this->actingAs($employee)->get('/dashboards/ceo/traffic-data/breakdowns')->assertForbidden();
    }

    public function test_returns_mapped_websites_from_the_shared_registry()
    {
        $this->app->instance(BigQueryRunner::class, new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                return [
                    ['website_domain' => 'exotickenya.com', 'website_name' => 'Exotic Kenya', 'country' => 'Kenya'],
                    ['website_domain' => 'exoticuganda.com', 'website_name' => 'Exotic Uganda', 'country' => 'Uganda'],
                ];
            }
        });

        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->getJson('/dashboards/ceo/traffic-data/websites')
            ->assertOk()
            ->assertJson([
                'websites' => [
                    ['website_id' => 'exotickenya.com', 'domain' => 'exotickenya.com', 'name' => 'Exotic Kenya'],
                    ['website_id' => 'exoticuganda.com', 'domain' => 'exoticuganda.com', 'name' => 'Exotic Uganda'],
                ],
            ]);
    }

    public function test_returns_ga4_and_gsc_kpis_and_trend_for_the_selected_website()
    {
        $this->app->instance(BigQueryRunner::class, new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                if (str_contains($sql, 'vw_daily_website_metrics')) {
                    return [['event_date' => '2026-07-01', 'users' => 100, 'sessions' => 120, 'engaged_sessions' => 60]];
                }

                if (str_contains($sql, 'vw_key_events')) {
                    return [['key_events' => 10]];
                }

                if (str_contains($sql, 'gsc_daily_site')) {
                    return [['data_date' => '2026-07-01', 'clicks' => 5, 'impressions' => 50, 'average_position' => 3.0]];
                }

                return [];
            }
        });

        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data?'.http_build_query([
            'website_domain' => 'exotickenya.com',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-07',
        ]))->assertOk();

        $response->assertJson([
            'ga4' => ['source' => ['status' => 'ok', 'error' => null]],
            'gsc' => ['source' => ['status' => 'ok', 'error' => null]],
        ]);
        $this->assertSame(100, $response->json('ga4.kpis.aggregate_property_users.current'));
        $this->assertSame(5, $response->json('gsc.kpis.clicks.current'));
    }

    public function test_all_platforms_aggregates_across_every_website()
    {
        $this->app->instance(BigQueryRunner::class, new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                if (str_contains($sql, 'website_domain =') || str_contains($sql, 'domain =')) {
                    throw new RuntimeException('should not filter to a single website for the "all" option');
                }

                if (str_contains($sql, 'vw_daily_website_metrics')) {
                    return [['event_date' => '2026-07-01', 'users' => 300, 'sessions' => 400, 'engaged_sessions' => 200]];
                }

                if (str_contains($sql, 'vw_key_events')) {
                    return [['key_events' => 20]];
                }

                return [];
            }
        });

        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data?'.http_build_query([
            'website_domain' => 'all',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-07',
        ]))->assertOk();

        $this->assertSame(300, $response->json('ga4.kpis.aggregate_property_users.current'));
    }

    public function test_returns_breakdowns_and_per_site_comparison()
    {
        $this->app->instance(BigQueryRunner::class, new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                if (str_contains($sql, 'metadata.websites')) {
                    return [['website_domain' => 'exotickenya.com', 'website_name' => 'Exotic Kenya', 'country' => 'Kenya']];
                }

                if (str_contains($sql, 'vw_traffic_sources')) {
                    return [['source' => 'google', 'medium' => 'organic', 'users' => 60]];
                }

                if (str_contains($sql, 'vw_device_breakdown')) {
                    return [['device_category' => 'mobile', 'users' => 70]];
                }

                if (str_contains($sql, 'vw_landing_pages')) {
                    return [['page_location' => 'https://exotickenya.com/', 'users' => 60, 'page_views' => 80]];
                }

                if (str_contains($sql, 'vw_geo_breakdown')) {
                    return [['user_country' => 'Kenya', 'users' => 60]];
                }

                if (str_contains($sql, 'vw_key_events') && str_contains($sql, 'GROUP BY key_event')) {
                    return [['key_event' => 'purchase', 'key_event_category' => 'ecommerce', 'key_event_count' => 3, 'users' => 3]];
                }

                if (str_contains($sql, 'gsc_daily_queries')) {
                    return [['query' => 'exotic kenya', 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'average_position' => 2.0]];
                }

                if (str_contains($sql, 'gsc_daily_pages')) {
                    return [['url' => 'https://exotickenya.com/', 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1]];
                }

                if (str_contains($sql, 'gsc_daily_countries')) {
                    return [['country' => 'Kenya', 'clicks' => 10, 'impressions' => 100]];
                }

                if (str_contains($sql, 'gsc_daily_devices')) {
                    return [['device' => 'mobile', 'clicks' => 10, 'impressions' => 100]];
                }

                if (str_contains($sql, 'vw_daily_website_metrics') && str_contains($sql, 'GROUP BY website_domain')) {
                    return [['website_domain' => 'exotickenya.com', 'users' => 300, 'sessions' => 400, 'engagement_rate' => 0.5]];
                }

                if (str_contains($sql, 'gsc_daily_site') && str_contains($sql, 'GROUP BY domain')) {
                    return [['domain' => 'exotickenya.com', 'clicks' => 30, 'impressions' => 300, 'average_position' => 2.5]];
                }

                return [];
            }
        });

        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data/breakdowns?'.http_build_query([
            'website_domain' => 'all',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-07',
        ]))->assertOk();

        $response->assertJson([
            'ga4' => [
                'traffic_sources' => [['source' => 'google', 'medium' => 'organic', 'users' => 60]],
                'devices' => [['device_category' => 'mobile', 'users' => 70]],
                'landing_pages' => [['page_location' => 'https://exotickenya.com/', 'users' => 60, 'page_views' => 80]],
                'locations' => [['user_country' => 'Kenya', 'users' => 60]],
                'key_events' => [['key_event' => 'purchase', 'key_event_category' => 'ecommerce', 'key_event_count' => 3, 'users' => 3]],
            ],
            'gsc' => [
                'queries' => [['query' => 'exotic kenya', 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'average_position' => 2.0]],
                'pages' => [['url' => 'https://exotickenya.com/', 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1]],
                'countries' => [['country' => 'Kenya', 'clicks' => 10, 'impressions' => 100]],
                'devices' => [['device' => 'mobile', 'clicks' => 10, 'impressions' => 100]],
            ],
            'comparison' => [
                'rows' => [[
                    'website_id' => 'exotickenya.com',
                    'name' => 'Exotic Kenya',
                    'domain' => 'exotickenya.com',
                    'ga4' => ['users' => 300, 'sessions' => 400, 'engagement_rate' => 0.5],
                    'gsc' => ['clicks' => 30, 'impressions' => 300, 'average_position' => 2.5],
                ]],
            ],
        ]);
    }

    public function test_query_failure_is_reported_per_source_without_breaking_the_page()
    {
        $this->app->instance(BigQueryRunner::class, new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                throw new RuntimeException('Access Denied: Table burnished-stone-421212:analytics_352711530.events_*');
            }
        });

        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data?'.http_build_query([
            'website_domain' => 'exotickenya.com',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-07',
        ]))
            ->assertOk()
            ->assertJson([
                'ga4' => ['source' => ['status' => 'failed', 'error' => 'Access Denied: Table burnished-stone-421212:analytics_352711530.events_*']],
                'gsc' => ['source' => ['status' => 'failed', 'error' => 'Access Denied: Table burnished-stone-421212:analytics_352711530.events_*']],
            ]);
    }

    private function bindCountingRunner(): void
    {
        $this->recordedCalls = [];
        $test = $this;

        $this->app->instance(BigQueryRunner::class, new class($test) implements BigQueryRunner
        {
            public function __construct(private TrafficDataControllerTest $test) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                $this->test->recordedCalls[] = $sql;

                if (str_contains($sql, 'vw_daily_website_metrics')) {
                    return [['event_date' => '2026-07-01', 'users' => 100, 'sessions' => 120, 'engaged_sessions' => 60]];
                }

                if (str_contains($sql, 'vw_key_events')) {
                    return [['key_events' => 10]];
                }

                if (str_contains($sql, 'gsc_daily_site')) {
                    return [['data_date' => '2026-07-01', 'clicks' => 5, 'impressions' => 50, 'average_position' => 3.0]];
                }

                return [];
            }
        });
    }

    public function test_a_second_request_with_the_same_filters_reuses_the_cache_instead_of_requerying_bigquery()
    {
        $this->bindCountingRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $params = http_build_query(['website_domain' => 'exotickenya.com', 'date_from' => '2026-07-01', 'date_to' => '2026-07-07']);
        $this->actingAs($ceo)->getJson("/dashboards/ceo/traffic-data?{$params}")->assertOk();
        $this->actingAs($ceo)->getJson("/dashboards/ceo/traffic-data?{$params}")->assertOk();

        $ga4Calls = collect($this->recordedCalls)->filter(fn ($sql) => str_contains($sql, 'vw_daily_website_metrics'))->count();
        $this->assertSame(1, $ga4Calls, 'expected the second identical request to be served from cache, not BigQuery again');
    }

    public function test_the_refresh_flag_busts_the_cache_and_requeries_bigquery()
    {
        $this->bindCountingRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $params = http_build_query(['website_domain' => 'exotickenya.com', 'date_from' => '2026-07-01', 'date_to' => '2026-07-07']);
        $this->actingAs($ceo)->getJson("/dashboards/ceo/traffic-data?{$params}")->assertOk();
        $this->actingAs($ceo)->getJson("/dashboards/ceo/traffic-data?{$params}&refresh=1")->assertOk();

        $ga4Calls = collect($this->recordedCalls)->filter(fn ($sql) => str_contains($sql, 'vw_daily_website_metrics'))->count();
        $this->assertSame(2, $ga4Calls, 'refresh=1 must force a live requery even though a cached value exists');
    }

    public function test_the_website_list_is_shared_with_marketing_statistics_cache()
    {
        $this->bindCountingRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data/websites')->assertOk();
        $this->actingAs($ceo)->getJson('/marketing-statistics/ga4')->assertOk();

        $registryCalls = collect($this->recordedCalls)->filter(fn ($sql) => str_contains($sql, 'metadata.websites'))->count();
        $this->assertSame(1, $registryCalls, 'both screens should read the same cached registry entry');
    }
}
