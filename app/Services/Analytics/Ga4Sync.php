<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsSyncLog;
use App\Models\Website;
use App\Models\WebsiteGa4DailyMetric;
use App\Services\Analytics\Contracts\BigQueryRunner;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Pulls one day of GA4 BigQuery Export data per website and rolls it up
 * into website_ga4_daily_metrics. The queries below follow the standard
 * GA4 export schema (events_* wildcard tables) and are a first pass —
 * validate them against real data once credentials are in place.
 */
class Ga4Sync
{
    public function __construct(private BigQueryRunner $runner) {}

    /** @return Collection<int, AnalyticsSyncLog> */
    public function syncDate(CarbonInterface $date): Collection
    {
        return Website::query()
            ->whereNotNull('ga4_property_id')
            ->get()
            ->map(fn (Website $website) => $this->syncWebsite($website, $date));
    }

    public function syncWebsite(Website $website, CarbonInterface $date): AnalyticsSyncLog
    {
        $startedAt = now();

        if (! $this->runner->isConfigured()) {
            return $this->logFailure($website, $startedAt, 'BigQuery is not configured: set BIGQUERY_PROJECT_ID (and credentials) in .env.');
        }

        if (! preg_match('/^\d+$/', (string) $website->ga4_property_id)) {
            return $this->logFailure($website, $startedAt, "Invalid GA4 property id \"{$website->ga4_property_id}\".");
        }

        $dataset = str_replace('{property_id}', $website->ga4_property_id, config('analytics.ga4.dataset_pattern'));
        $eventDate = $date->format('Ymd');

        try {
            $summary = $this->runner->rows($this->summarySql($dataset), ['event_date' => $eventDate]);
            $keyEvents = $this->runner->rows($this->keyEventsSql($dataset), [
                'event_date' => $eventDate,
                'key_events' => config('analytics.ga4.key_events'),
            ]);
        } catch (Throwable $e) {
            return $this->logFailure($website, $startedAt, $e->getMessage());
        }

        $totals = $summary[0] ?? ['users' => 0, 'sessions' => 0, 'engaged_sessions' => 0];

        WebsiteGa4DailyMetric::query()->updateOrCreate(
            ['website_id' => $website->id, 'date' => $date->toDateString()],
            [
                'users' => $totals['users'] ?? 0,
                'sessions' => $totals['sessions'] ?? 0,
                'engaged_sessions' => $totals['engaged_sessions'] ?? 0,
                'key_events' => collect($keyEvents)->pluck('total', 'event_name')->toArray(),
            ],
        );

        return AnalyticsSyncLog::create([
            'source' => AnalyticsSyncLog::SOURCE_GA4,
            'website_id' => $website->id,
            'status' => AnalyticsSyncLog::STATUS_SUCCESS,
            'records_processed' => count($summary) + count($keyEvents),
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }

    private function logFailure(Website $website, CarbonInterface $startedAt, string $message): AnalyticsSyncLog
    {
        return AnalyticsSyncLog::create([
            'source' => AnalyticsSyncLog::SOURCE_GA4,
            'website_id' => $website->id,
            'status' => AnalyticsSyncLog::STATUS_FAILED,
            'records_processed' => 0,
            'error_message' => $message,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }

    private function summarySql(string $dataset): string
    {
        return <<<SQL
            SELECT
              COUNT(DISTINCT user_pseudo_id) AS users,
              COUNT(DISTINCT CONCAT(user_pseudo_id, '-', (SELECT value.int_value FROM UNNEST(event_params) WHERE key = 'ga_session_id'))) AS sessions,
              COUNT(DISTINCT IF(event_name = 'user_engagement', CONCAT(user_pseudo_id, '-', (SELECT value.int_value FROM UNNEST(event_params) WHERE key = 'ga_session_id')), NULL)) AS engaged_sessions
            FROM `{$dataset}.events_*`
            WHERE _TABLE_SUFFIX = @event_date
            SQL;
    }

    private function keyEventsSql(string $dataset): string
    {
        return <<<SQL
            SELECT event_name, COUNT(*) AS total
            FROM `{$dataset}.events_*`
            WHERE _TABLE_SUFFIX = @event_date
              AND event_name IN UNNEST(@key_events)
            GROUP BY event_name
            SQL;
    }
}
