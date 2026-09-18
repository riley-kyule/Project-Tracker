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
        Http::fake([
            'api.popcash.test/*' => Http::response([
                'data' => [
                    ['date' => '2026-09-15', 'spend' => '12.50', 'cpm' => '3.25', 'impressions' => 4000],
                ],
            ], 200),
        ]);

        $client = new PopcashApiClient;
        $rows = $client->dailyStats('camp-1', Carbon::parse('2026-09-15'), Carbon::parse('2026-09-15'));

        $this->assertSame([
            ['data_date' => '2026-09-15', 'money_spent' => 12.5, 'cpm' => 3.25, 'impressions' => 4000],
        ], $rows);

        Http::assertSent(fn ($request) => $request['campaign_id'] === 'camp-1'
            && $request['date_from'] === '2026-09-15'
            && $request['date_to'] === '2026-09-15'
            && $request->hasHeader('Authorization', 'Bearer secret-key'));
    }

    public function test_a_non_successful_response_throws()
    {
        Http::fake(['api.popcash.test/*' => Http::response('nope', 500)]);

        $this->expectException(RuntimeException::class);

        (new PopcashApiClient)->dailyStats('camp-1', Carbon::parse('2026-09-15'), Carbon::parse('2026-09-15'));
    }
}
