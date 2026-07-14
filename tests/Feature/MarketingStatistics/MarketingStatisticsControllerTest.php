<?php

namespace Tests\Feature\MarketingStatistics;

use App\Models\User;
use App\Services\Analytics\Contracts\BigQueryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MarketingStatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    /** Public so the anonymous fake below (a different class) can append to it. */
    public array $recordedCalls = [];

    private function bindFakeRunner(): void
    {
        $this->recordedCalls = [];
        $test = $this;

        $runner = new class($test) implements BigQueryRunner
        {
            public function __construct(private MarketingStatisticsControllerTest $test) {}

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

                if (str_contains($sql, 'vw_daily_website_metrics')) {
                    // Distinguish current vs. comparison period by date_from.
                    $users = $parameters['date_from'] === '2026-06-24' ? 50 : 100;

                    return [['event_date' => $parameters['date_from'], 'users' => $users, 'sessions' => $users + 10, 'engaged_sessions' => 5]];
                }

                if (str_contains($sql, 'vw_key_events') && str_contains($sql, 'GROUP BY')) {
                    return [
                        ['key_event' => 'whatsapp_click', 'key_event_category' => 'contact', 'key_event_count' => 12, 'users' => 10],
                        ['key_event' => 'call_now', 'key_event_category' => 'contact', 'key_event_count' => 5, 'users' => 4],
                    ];
                }

                if (str_contains($sql, 'vw_key_events')) {
                    return [['key_events' => 7]];
                }

                if (str_contains($sql, 'vw_traffic_sources') || str_contains($sql, 'vw_device_breakdown')
                    || str_contains($sql, 'vw_landing_pages') || str_contains($sql, 'vw_geo_breakdown')) {
                    return [];
                }

                if (str_contains($sql, 'gsc_daily_site') && str_contains($sql, 'SAFE_DIVIDE')) {
                    return [['data_date' => $parameters['date_from'], 'clicks' => 20, 'impressions' => 200, 'average_position' => 5.0]];
                }

                if (str_contains($sql, 'gsc_daily_site') && str_contains($sql, 'DATE_DIFF')) {
                    return [['domain' => 'a.example.com', 'latest_date' => now()->subDay()->toDateString(), 'days_behind' => 1]];
                }

                if (str_contains($sql, 'gsc_daily_queries') || str_contains($sql, 'gsc_daily_pages')
                    || str_contains($sql, 'gsc_daily_countries') || str_contains($sql, 'gsc_daily_devices')) {
                    return [];
                }

                if (str_contains($sql, 'ahrefs_daily_site')) {
                    throw new RuntimeException('Not found: Table burnished-stone-421212:analytics_core.ahrefs_daily_site');
                }

                return [];
            }
        };

        $this->app->instance(BigQueryRunner::class, $runner);
    }

    public function test_guests_are_redirected_to_login()
    {
        foreach (['', 'ga4', 'gsc', 'ahrefs', 'comparison', 'freshness'] as $tab) {
            $this->get('/marketing-statistics/'.$tab)->assertRedirect('/login');
        }
    }

    public function test_users_without_the_permission_are_forbidden()
    {
        $this->bindFakeRunner();
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/marketing-statistics')->assertForbidden();
    }

    public function test_ceo_administrator_and_marketing_roles_can_access()
    {
        $this->bindFakeRunner();

        foreach (['CEO', 'Administrator', 'Marketing'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get('/marketing-statistics')->assertOk();
        }
    }

    public function test_single_website_selection_filters_ga4_and_gsc_queries_to_that_website()
    {
        $this->bindFakeRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/marketing-statistics/ga4?website_id=a.example.com')->assertOk();

        $ga4Call = collect($this->recordedCalls)->first(fn ($call) => str_contains($call['sql'], 'vw_daily_website_metrics'));
        $this->assertNotNull($ga4Call, 'expected a vw_daily_website_metrics query to have run');
        $this->assertSame('a.example.com', $ga4Call['parameters']['website_domain']);
    }

    public function test_all_sites_selection_aggregates_without_a_website_filter()
    {
        $this->bindFakeRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/marketing-statistics/gsc?website_id=all')->assertOk();

        $gscCall = collect($this->recordedCalls)
            ->first(fn ($call) => str_contains($call['sql'], 'gsc_daily_site') && str_contains($call['sql'], 'SAFE_DIVIDE'));
        $this->assertNotNull($gscCall, 'expected a gsc_daily_site summary query to have run');
        $this->assertArrayNotHasKey('domain', $gscCall['parameters']);
    }

    public function test_custom_date_range_is_applied_and_echoed_back()
    {
        $this->bindFakeRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->get(
            '/marketing-statistics/gsc?range=custom&date_from=2026-06-01&date_to=2026-06-10',
        )->assertOk();

        $selected = $response->viewData('page')['props']['selected'];
        $this->assertSame('custom', $selected['range']);
        $this->assertSame('2026-06-01', $selected['date_from']);
        $this->assertSame('2026-06-10', $selected['date_to']);

        $gscCall = collect($this->recordedCalls)
            ->first(fn ($call) => str_contains($call['sql'], 'gsc_daily_site') && str_contains($call['sql'], 'SAFE_DIVIDE'));
        $this->assertSame('2026-06-01', $gscCall['parameters']['date_from']);
        $this->assertSame('2026-06-10', $gscCall['parameters']['date_to']);
    }

    public function test_previous_period_comparison_computes_the_immediately_preceding_range()
    {
        $this->bindFakeRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->get(
            '/marketing-statistics/ga4?range=custom&date_from=2026-07-01&date_to=2026-07-07&comparison=previous_period',
        )->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertSame('2026-06-24', $props['selected']['compare_from']);
        $this->assertSame('2026-06-30', $props['selected']['compare_to']);

        // The fake returns different `users` depending on date_from, so a
        // correct comparison range also shows up as a correct KPI delta.
        $this->assertSame(100, $props['kpis']['aggregate_property_users']['current']);
        $this->assertSame(50, $props['kpis']['aggregate_property_users']['comparison']);
    }

    public function test_previous_year_comparison_computes_the_same_range_one_year_back()
    {
        $this->bindFakeRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->get(
            '/marketing-statistics/ga4?range=custom&date_from=2026-07-01&date_to=2026-07-07&comparison=previous_year',
        )->assertOk();

        $selected = $response->viewData('page')['props']['selected'];
        $this->assertSame('2025-07-01', $selected['compare_from']);
        $this->assertSame('2025-07-07', $selected['compare_to']);
    }

    /**
     * GSC/Ahrefs on the Overview page — and breakdowns on the GA4/GSC pages
     * — are deferred (Inertia::defer) so the page paints immediately
     * instead of waiting on every source. Simulates the follow-up partial
     * reload the frontend's <Deferred> fires automatically, since a plain
     * GET (as the browser's first request) never evaluates them.
     */
    private function partialReloadHeaders(string $component, string $only, string $version): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => $component,
            'X-Inertia-Partial-Data' => $only,
        ];
    }

    public function test_a_failed_source_is_reported_without_breaking_the_page()
    {
        $this->bindFakeRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        // GA4 is eager; Ahrefs has no BigQuery table yet — the fake throws for it, GSC succeeds.
        $response = $this->actingAs($ceo)->get('/marketing-statistics')->assertOk();
        $this->assertSame('ok', $response->viewData('page')['props']['ga4_source']['status']);

        $deferred = $this->actingAs($ceo)->get(
            '/marketing-statistics',
            $this->partialReloadHeaders('marketing-statistics/overview', 'gsc,ahrefs', $response->viewData('page')['version']),
        )->assertOk();

        $props = $deferred->json('props');
        $this->assertSame('ok', $props['gsc']['source']['status']);
        $this->assertSame('failed', $props['ahrefs']['source']['status']);
        $this->assertNotNull($props['ahrefs']['source']['error']);
    }

    public function test_selected_filters_are_mirrored_back_exactly_for_url_persistence()
    {
        $this->bindFakeRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $query = http_build_query([
            'website_id' => 'site-b',
            'range' => 'custom',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-15',
            'comparison' => 'custom',
            'compare_from' => '2026-04-01',
            'compare_to' => '2026-04-15',
        ]);

        $response = $this->actingAs($ceo)->get("/marketing-statistics/gsc?{$query}")->assertOk();

        // The frontend filter bar reads `selected` and writes the identical
        // keys back into the URL via router.get() — this is the contract
        // that makes filters survive a reload/shared link.
        $this->assertSame([
            'website_id' => 'site-b',
            'range' => 'custom',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-15',
            'comparison' => 'custom',
            'compare_from' => '2026-04-01',
            'compare_to' => '2026-04-15',
        ], $response->viewData('page')['props']['selected']);
    }

    public function test_ga4_initial_load_skips_the_deferred_breakdown_queries()
    {
        $this->bindFakeRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/marketing-statistics/ga4')->assertOk();

        $breakdownCall = collect($this->recordedCalls)->first(
            fn ($call) => str_contains($call['sql'], 'vw_traffic_sources')
                || str_contains($call['sql'], 'vw_device_breakdown')
                || str_contains($call['sql'], 'vw_landing_pages')
                || str_contains($call['sql'], 'vw_geo_breakdown')
                || (str_contains($call['sql'], 'vw_key_events') && str_contains($call['sql'], 'GROUP BY')),
        );

        $this->assertNull($breakdownCall, 'breakdown queries should not run until the deferred partial reload requests them');
    }

    public function test_ga4_page_includes_a_key_events_breakdown()
    {
        $this->bindFakeRunner();
        $ceo = User::factory()->create()->assignRole('CEO');

        $initial = $this->actingAs($ceo)->get('/marketing-statistics/ga4')->assertOk();

        $response = $this->actingAs($ceo)->get(
            '/marketing-statistics/ga4',
            $this->partialReloadHeaders('marketing-statistics/ga4', 'breakdowns', $initial->viewData('page')['version']),
        )->assertOk();

        $keyEvents = $response->json('props.breakdowns.key_events');
        $this->assertSame('whatsapp_click', $keyEvents[0]['key_event']);
        $this->assertSame(12, $keyEvents[0]['key_event_count']);
    }

    public function test_comparison_issues_a_fixed_number_of_queries_regardless_of_website_count()
    {
        $runner = new class implements BigQueryRunner
        {
            public array $calls = [];

            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                $this->calls[] = $sql;

                if (str_contains($sql, 'metadata.websites')) {
                    return [
                        ['website_domain' => 'a.example.com', 'website_name' => 'Site A', 'country' => null],
                        ['website_domain' => 'b.example.com', 'website_name' => 'Site B', 'country' => null],
                        ['website_domain' => 'c.example.com', 'website_name' => 'Site C', 'country' => null],
                    ];
                }

                if (str_contains($sql, 'vw_daily_website_metrics') && str_contains($sql, 'IN UNNEST')) {
                    // Deliberately no row for c.example.com — proves a missing
                    // domain degrades to null instead of erroring.
                    return [
                        ['website_domain' => 'a.example.com', 'users' => 100, 'sessions' => 150, 'engagement_rate' => 0.5],
                        ['website_domain' => 'b.example.com', 'users' => 200, 'sessions' => 250, 'engagement_rate' => 0.6],
                    ];
                }

                if (str_contains($sql, 'gsc_daily_site') && str_contains($sql, 'IN UNNEST')) {
                    return [
                        ['domain' => 'a.example.com', 'clicks' => 10, 'impressions' => 100, 'average_position' => 4.0],
                    ];
                }

                return [];
            }
        };

        $this->app->instance(BigQueryRunner::class, $runner);
        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->get('/marketing-statistics/comparison')->assertOk();

        $batchedCalls = collect($runner->calls)->filter(fn ($sql) => str_contains($sql, 'IN UNNEST'))->count();
        $this->assertSame(2, $batchedCalls, 'expected exactly one batched GA4 query and one batched GSC query, regardless of website count');

        $rows = collect($response->viewData('page')['props']['rows']);
        $siteA = $rows->firstWhere('domain', 'a.example.com');
        $this->assertSame(100, $siteA['ga4']['users']);
        $this->assertSame(10, $siteA['gsc']['clicks']);

        $siteC = $rows->firstWhere('domain', 'c.example.com');
        $this->assertNull($siteC['ga4']);
        $this->assertNull($siteC['gsc']);
    }
}
