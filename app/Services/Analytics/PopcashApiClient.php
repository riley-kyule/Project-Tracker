<?php

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * UNVERIFIED — Popcash's public "Statistics API" endpoint path, auth
 * placement (header vs query param), and response field names have not
 * been confirmed against Popcash's own docs. The shape below (campaign_id
 * + date_from/date_to → rows of date/spend/cpm/impressions) is a
 * reasonable guess mirroring their dashboard's exposed stats, not a
 * confirmed contract — verify and adjust before flipping
 * ANALYTICS_POPCASH_ENABLED on in production. See AhrefsReportQuery for
 * the same "speculative, gated off" precedent.
 */
class PopcashApiClient
{
    /**
     * Returns every field Popcash's response includes for each row, not
     * just the three EWMS currently has typed columns/KPIs for — `raw`
     * carries the untouched row so nothing the API returns is silently
     * discarded (clicks, conversions, revenue, zone/campaign breakdown,
     * whatever else Popcash exposes) even before we know its exact schema
     * well enough to promote a field to its own column. See
     * AnalyticsSyncService::syncPopcash(), which persists `raw` as-is.
     *
     * @return array<int, array{data_date: string, money_spent: float, cpm: float, impressions: int, raw: array}>
     */
    public function dailyStats(string $campaignId, Carbon $from, Carbon $to): array
    {
        $response = Http::baseUrl(config('analytics.api.popcash.base_url'))
            ->withHeaders(['Authorization' => 'Bearer '.config('analytics.api.popcash.api_key')])
            ->acceptJson()
            ->timeout(config('analytics.api.popcash.request_timeout'))
            ->retry(4, fn ($attempt) => $attempt * 1000, throw: false)
            ->get('/statistics', [
                'campaign_id' => $campaignId,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Popcash Statistics API {$response->status()}: {$response->body()}");
        }

        return collect($response->json('data', []))->map(fn (array $row) => [
            'data_date' => $row['date'],
            'money_spent' => (float) $row['spend'],
            'cpm' => (float) $row['cpm'],
            'impressions' => (int) $row['impressions'],
            'raw' => $row,
        ])->all();
    }
}
