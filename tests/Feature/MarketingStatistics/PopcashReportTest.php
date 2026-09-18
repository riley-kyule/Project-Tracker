<?php

namespace Tests\Feature\MarketingStatistics;

use App\Models\User;
use App\Services\Analytics\PopcashReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PopcashReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PopcashReportQuery's raw SQL uses Postgres-only casts (`::bigint`),
     * matching the exact style of the existing TrafficDashboardQuery /
     * GscReportQuery it was modelled on — those aren't exercised against a
     * real database in this suite either (tests run on sqlite, production
     * is Postgres). Faking at the query-class boundary is the same
     * approach MarketingStatisticsControllerTest uses for GA4/GSC/Ahrefs
     * via a fake BigQueryRunner.
     */
    private function bindFakePopcash(array $rows): void
    {
        $fake = new class($rows) extends PopcashReportQuery
        {
            public function __construct(private array $rows) {}

            public function dailyRows(string|array|null $domain, Carbon $from, Carbon $to): array
            {
                return $this->rows;
            }
        };

        $this->app->instance(PopcashReportQuery::class, $fake);
    }

    public function test_popcash_tab_404s_and_is_hidden_when_disabled()
    {
        config(['analytics.api.popcash.enabled' => false]);
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/marketing-statistics/popcash')->assertNotFound();

        $this->actingAs($ceo)->get('/marketing-statistics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('popcash_enabled', false)->missing('popcash'));
    }

    public function test_popcash_report_aggregates_spend_impressions_and_weighted_cpm()
    {
        config(['analytics.api.popcash.enabled' => true]);
        $ceo = User::factory()->create()->assignRole('CEO');
        $this->bindFakePopcash([
            ['data_date' => now()->subDays(2)->toDateString(), 'money_spent' => 1.0, 'impressions' => 100, 'cpm' => 10.0],
            ['data_date' => now()->subDays(1)->toDateString(), 'money_spent' => 200.0, 'impressions' => 100000, 'cpm' => 2.0],
        ]);

        $response = $this->actingAs($ceo)->get('/marketing-statistics/popcash')->assertOk();

        $kpis = $response->viewData('page')['props']['kpis'];
        $this->assertSame(201.0, $kpis['money_spent']['current']);
        $this->assertSame(100100, $kpis['impressions']['current']);
        // Weighted by impressions, not a naive average of the two rows' own CPM (which would be $6).
        $this->assertEqualsWithDelta((201.0 / 100100) * 1000, $kpis['cpm']['current'], 0.0001);
    }

    public function test_popcash_report_with_no_rows_is_reported_as_missing_not_a_crash()
    {
        config(['analytics.api.popcash.enabled' => true]);
        $ceo = User::factory()->create()->assignRole('CEO');
        $this->bindFakePopcash([]);

        $response = $this->actingAs($ceo)->get('/marketing-statistics/popcash')->assertOk();

        $this->assertSame('missing', $response->viewData('page')['props']['source']['status']);
        $this->assertSame(0, $response->viewData('page')['props']['kpis']['money_spent']['current']);
        $this->assertNull($response->viewData('page')['props']['kpis']['cpm']['current']);
    }
}
