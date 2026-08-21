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

    /** Minimal fake: two registered sites, both GA4 and GSC return real-shaped rows for anything else. */
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

                if (str_contains($sql, 'vw_daily_website_metrics')) {
                    return [['event_date' => $parameters['date_from'], 'users' => 10, 'sessions' => 20, 'engaged_sessions' => 5]];
                }

                if (str_contains($sql, 'vw_key_events')) {
                    return [['key_events' => 1]];
                }

                if (str_contains($sql, 'gsc_daily_site')) {
                    return [['data_date' => $parameters['date_from'], 'clicks' => 1, 'impressions' => 10, 'average_position' => 3.0]];
                }

                return [];
            }
        };

        $this->app->instance(BigQueryRunner::class, $runner);
    }

    public function test_it_warms_ga4_and_gsc_for_every_website_plus_the_all_sites_aggregate()
    {
        $this->bindFakeRunner();

        $this->artisan('ewms:warm-analytics-cache')->assertSuccessful();

        // 1 registry + (GA4: dailyRows + keyEventsTotal, GSC: dailyRows) per target,
        // 3 targets (all sites, a.example.com, b.example.com).
        $ga4Calls = collect($this->recordedCalls)->filter(fn ($call) => str_contains($call['sql'], 'vw_daily_website_metrics'))->count();
        $gscCalls = collect($this->recordedCalls)->filter(fn ($call) => str_contains($call['sql'], 'gsc_daily_site'))->count();

        $this->assertSame(3, $ga4Calls);
        $this->assertSame(3, $gscCalls);
    }

    public function test_a_page_view_after_warming_reuses_the_cache_and_makes_no_further_bigquery_calls()
    {
        $this->bindFakeRunner();
        $this->artisan('ewms:warm-analytics-cache')->assertSuccessful();

        $callsAfterWarming = count($this->recordedCalls);

        $ceo = User::factory()->create()->assignRole('CEO');
        $this->actingAs($ceo)->get('/marketing-statistics/gsc?website_id=a.example.com')->assertOk();

        $this->assertCount($callsAfterWarming, $this->recordedCalls, 'a page view matching the warmed default range should be served entirely from cache');
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
