<?php

namespace Tests\Feature\Analytics;

use App\Models\Website;
use App\Services\Analytics\AnalyticsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsSyncServicePopcashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'analytics.api.popcash.base_url' => 'https://api.popcash.test',
            'analytics.api.popcash.api_key' => 'secret-key',
            'analytics.api.popcash.request_timeout' => 30,
        ]);
    }

    /**
     * Popcash's reports endpoint returns a date range in one response — a
     * 3-day sync must make exactly one HTTP call, not three, and still
     * write one row per day (including days the API didn't return anything
     * for, so a genuinely zero-spend day isn't confused with "never synced").
     */
    public function test_a_multi_day_range_makes_a_single_api_call_and_writes_one_row_per_day()
    {
        $website = Website::factory()->create(['popcash_campaign_id' => 'camp-1']);

        Http::fake([
            'api.popcash.test/*' => Http::response([
                'data' => [
                    ['date' => '2026-09-01', 'spend' => '10', 'cpm' => '2', 'impressions' => 5000],
                    // 2026-09-02 deliberately missing from the response — a real no-spend day.
                    ['date' => '2026-09-03', 'spend' => '20', 'cpm' => '4', 'impressions' => 5000],
                ],
            ], 200),
        ]);

        $sync = app(AnalyticsSyncService::class);
        $result = $sync->syncPopcashRange($website, '2026-09-01', '2026-09-03');

        Http::assertSentCount(1);
        $this->assertCount(3, $result);

        $rows = DB::table('analytics_popcash_daily_spend')->where('website_id', $website->id)->orderBy('data_date')->get();
        $this->assertCount(2, $rows); // only the two days with an actual row inserted
        $this->assertEqualsCanonicalizing(['2026-09-01', '2026-09-03'], $rows->pluck('data_date')->map(fn ($d) => (string) $d)->all());

        $runs = DB::table('analytics_sync_runs')->where('website_id', $website->id)->where('source', 'popcash')->get();
        $this->assertCount(3, $runs); // one tracked run per day in the range, even the empty one
        $this->assertTrue($runs->every(fn ($r) => $r->status === 'success'));
    }

    public function test_a_website_without_a_campaign_id_is_skipped_without_an_api_call()
    {
        $website = Website::factory()->create(['popcash_campaign_id' => null]);

        Http::fake(['api.popcash.test/*' => Http::response(['data' => []], 200)]);

        $result = app(AnalyticsSyncService::class)->syncPopcashRange($website, '2026-09-01', '2026-09-03');

        Http::assertNothingSent();
        $this->assertSame([], $result);
    }
}
