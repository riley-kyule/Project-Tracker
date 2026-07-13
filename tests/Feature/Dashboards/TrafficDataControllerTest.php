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

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/dashboards/ceo/traffic-data/websites')->assertRedirect('/login');
        $this->get('/dashboards/ceo/traffic-data')->assertRedirect('/login');
    }

    public function test_employees_cannot_view_traffic_data()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/dashboards/ceo/traffic-data/websites')->assertForbidden();
        $this->actingAs($employee)->get('/dashboards/ceo/traffic-data')->assertForbidden();
    }

    public function test_returns_unconfigured_state_when_bigquery_is_not_set_up()
    {
        config(['analytics.bigquery.project_id' => null]);
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data/websites')
            ->assertOk()
            ->assertJson(['configured' => false, 'websites' => []]);

        $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data?'.http_build_query([
            'website_domain' => 'exotickenya.com', 'date_from' => '2026-07-01', 'date_to' => '2026-07-07',
        ]))->assertOk()->assertJson(['configured' => false]);
    }

    public function test_returns_mapped_websites_when_configured()
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
                'configured' => true,
                'websites' => [
                    ['website_domain' => 'exotickenya.com', 'website_name' => 'Exotic Kenya', 'country' => 'Kenya'],
                    ['website_domain' => 'exoticuganda.com', 'website_name' => 'Exotic Uganda', 'country' => 'Uganda'],
                ],
            ]);
    }

    public function test_returns_summary_trend_and_breakdowns_with_correct_comparison_range()
    {
        $this->app->instance(BigQueryRunner::class, new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                if (str_contains($sql, 'vw_daily_website_metrics') && str_contains($sql, 'SUM(users)') && ! str_contains($sql, 'GROUP BY')) {
                    // Distinguish current vs. comparison period by date_from.
                    return $parameters['date_from'] === '2026-06-24'
                        ? [['users' => 50, 'sessions' => 60, 'engagement_rate' => 0.4]]
                        : [['users' => 100, 'sessions' => 120, 'engagement_rate' => 0.5]];
                }

                if (str_contains($sql, 'vw_key_events') && str_contains($sql, 'SUM(key_event_count)')) {
                    return $parameters['date_from'] === '2026-06-24'
                        ? [['key_events' => 5]]
                        : [['key_events' => 10]];
                }

                if (str_contains($sql, 'vw_daily_website_metrics') && str_contains($sql, 'GROUP BY event_date')) {
                    return [['event_date' => '2026-07-01', 'users' => 14, 'sessions' => 20]];
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

                return [];
            }
        });

        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data?'.http_build_query([
            'website_domain' => 'exotickenya.com',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-07',
            'comparison_period' => 'previous_period',
        ]))->assertOk();

        $response->assertJson([
            'configured' => true,
            'summary' => [
                'current' => ['users' => 100, 'sessions' => 120, 'key_events' => 10, 'engagement_rate' => 0.5],
                'comparison' => ['users' => 50, 'sessions' => 60, 'key_events' => 5, 'engagement_rate' => 0.4],
            ],
            'trend' => [['event_date' => '2026-07-01', 'users' => 14, 'sessions' => 20]],
            'trafficSources' => [['source' => 'google', 'medium' => 'organic', 'users' => 60]],
            'devices' => [['device_category' => 'mobile', 'users' => 70]],
            'landingPages' => [['page_location' => 'https://exotickenya.com/', 'users' => 60, 'page_views' => 80]],
            'locations' => [['user_country' => 'Kenya', 'users' => 60]],
        ]);
    }

    public function test_no_comparison_when_comparison_period_is_none()
    {
        $this->app->instance(BigQueryRunner::class, new class implements BigQueryRunner
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                if (str_contains($sql, 'vw_daily_website_metrics') && ! str_contains($sql, 'GROUP BY')) {
                    return [['users' => 100, 'sessions' => 120, 'engagement_rate' => 0.5]];
                }

                if (str_contains($sql, 'vw_key_events')) {
                    return [['key_events' => 10]];
                }

                return [];
            }
        });

        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->getJson('/dashboards/ceo/traffic-data?'.http_build_query([
            'website_domain' => 'exotickenya.com',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-07',
            'comparison_period' => 'none',
        ]))->assertOk()->assertJson(['summary' => ['comparison' => null]]);
    }

    public function test_query_failure_is_reported_gracefully()
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
            ->assertStatus(502)
            ->assertJson(['configured' => true, 'error' => 'Access Denied: Table burnished-stone-421212:analytics_352711530.events_*']);
    }
}
