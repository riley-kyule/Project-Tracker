<?php

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Base URL, auth header, and the `reports/advertiser/campaign/{id}`
 * endpoint are confirmed against a working community client
 * (github.com/nebaz/popcash-api) — Popcash has no public docs we could
 * find. The request body's date-range field names and the response's row
 * shape are still unconfirmed, so dailyStats() hedges by sending several
 * plausible field-name aliases and reading several plausible response
 * shapes — adjust once a real response has been seen (check `raw` on
 * analytics_popcash_daily_spend after a sync, or the error body a failed
 * sync logs to analytics_sync_runs, which echoes Popcash's own message).
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
            ->withHeaders(['X-Api-Key' => config('analytics.api.popcash.api_key')])
            ->acceptJson()
            ->timeout(config('analytics.api.popcash.request_timeout'))
            ->retry(4, fn ($attempt) => $attempt * 1000, throw: false)
            ->post("reports/advertiser/campaign/{$campaignId}", [
                // Unconfirmed field names — sending common aliases since
                // extra unrecognized fields are harmless on most JSON APIs.
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
            ]);

        if (! $response->successful() || $response->json('errors') !== null) {
            throw new RuntimeException("Popcash reports API {$response->status()}: {$response->body()}");
        }

        $payload = $response->json();
        $rows = $payload['data'] ?? $payload['reports']['items'] ?? $payload['items'] ?? $payload['results'] ?? [];

        return collect($rows)->map(fn (array $row) => [
            'data_date' => $row['date'] ?? $row['day'] ?? $from->toDateString(),
            'money_spent' => (float) ($row['spend'] ?? $row['cost'] ?? $row['amount'] ?? 0),
            'cpm' => (float) ($row['cpm'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? $row['views'] ?? 0),
            'raw' => $row,
        ])->all();
    }
}
