<?php

namespace Tests\Unit\Services\Analytics;

use App\Services\Analytics\PopcashApiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PopcashApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'analytics.api.popcash.base_url' => 'https://api.popcash.test',
            'analytics.api.popcash.api_key' => 'secret-key',
            'analytics.api.popcash.request_timeout' => 30,
        ]);
    }

    public function test_daily_stats_maps_the_response_into_the_expected_shape()
    {
        $apiRow = ['date' => '2026-09-15', 'spend' => '12.50', 'cpm' => '3.25', 'impressions' => 4000, 'clicks' => 120, 'conversions' => 3];

        Http::fake([
            'api.popcash.test/*' => Http::response(['data' => [$apiRow]], 200),
        ]);

        $client = new PopcashApiClient;
        $rows = $client->dailyStats('camp-1', Carbon::parse('2026-09-15'), Carbon::parse('2026-09-15'));

        $this->assertSame([
            ['data_date' => '2026-09-15', 'money_spent' => 12.5, 'cpm' => 3.25, 'impressions' => 4000, 'raw' => $apiRow],
        ], $rows);

        // Fields with no dedicated column yet (clicks, conversions) must
        // still survive somewhere — via `raw`, not silently dropped.
        $this->assertSame(120, $rows[0]['raw']['clicks']);
        $this->assertSame(3, $rows[0]['raw']['conversions']);

        // The API validates strictly and rejects unrecognized fields (confirmed
        // via a live 422) — the body must be exactly these three, nothing more.
        Http::assertSent(fn ($request) => $request->url() === 'https://api.popcash.test/reports/advertiser/campaign/camp-1'
            && $request->method() === 'POST'
            && $request->data() === ['startDate' => '2026-09-15', 'endDate' => '2026-09-15', 'reportType' => 'daily']
            && $request->hasHeader('X-Api-Key', 'secret-key'));
    }

    /** Popcash's response nesting isn't confirmed — dailyStats() also accepts `reports.items`, `items`, or `results`. */
    public function test_daily_stats_accepts_a_nested_reports_items_response_shape()
    {
        $apiRow = ['date' => '2026-09-15', 'spend' => '5', 'cpm' => '1', 'impressions' => 100];

        Http::fake([
            'api.popcash.test/*' => Http::response(['reports' => ['items' => [$apiRow]]], 200),
        ]);

        $rows = (new PopcashApiClient)->dailyStats('camp-1', Carbon::parse('2026-09-15'), Carbon::parse('2026-09-15'));

        $this->assertSame(100, $rows[0]['impressions']);
    }

    public function test_a_non_successful_response_throws()
    {
        Http::fake(['api.popcash.test/*' => Http::response('nope', 500)]);

        $this->expectException(RuntimeException::class);

        (new PopcashApiClient)->dailyStats('camp-1', Carbon::parse('2026-09-15'), Carbon::parse('2026-09-15'));
    }

    /** Popcash's wrapper convention returns app-level errors inside an `errors` key even alongside a 200 status. */
    public function test_a_200_response_with_an_errors_key_throws()
    {
        Http::fake([
            'api.popcash.test/*' => Http::response(['errors' => ['message' => 'The resource could not be located!', 'status' => 404]], 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new PopcashApiClient)->dailyStats('camp-1', Carbon::parse('2026-09-15'), Carbon::parse('2026-09-15'));
    }
}
