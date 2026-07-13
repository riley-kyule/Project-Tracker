<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsSyncLog;
use App\Models\Website;
use App\Models\WebsiteGscDailyMetric;
use App\Services\Analytics\Contracts\BigQueryRunner;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Pulls one day of Search Console Bulk Data Export data per website and
 * rolls it up into website_gsc_daily_metrics. `site_url` is filtered
 * explicitly because a domain property's dataset can hold rows for more
 * than one sub-property. The query is a first pass — validate it against
 * real data once credentials and a dataset are in place.
 */
class GscSync
{
    public function __construct(private BigQueryRunner $runner) {}

    /** @return Collection<int, AnalyticsSyncLog> */
    public function syncDate(CarbonInterface $date): Collection
    {
        return Website::query()
            ->whereNotNull('gsc_property')
            ->get()
            ->map(fn (Website $website) => $this->syncWebsite($website, $date));
    }

    public function syncWebsite(Website $website, CarbonInterface $date): AnalyticsSyncLog
    {
        $startedAt = now();

        if (! $this->runner->isConfigured()) {
            return $this->logFailure($website, $startedAt, 'BigQuery is not configured: set BIGQUERY_PROJECT_ID (and credentials) in .env.');
        }

        if (blank($website->gsc_bigquery_dataset)) {
            return $this->logFailure($website, $startedAt, 'No gsc_bigquery_dataset set for this website.');
        }

        try {
            $rows = $this->runner->rows($this->summarySql($website->gsc_bigquery_dataset), [
                'event_date' => $date->format('Y-m-d'),
                'site_url' => $website->gsc_property,
            ]);
        } catch (Throwable $e) {
            return $this->logFailure($website, $startedAt, $e->getMessage());
        }

        $totals = $rows[0] ?? ['clicks' => 0, 'impressions' => 0, 'ctr' => null, 'position' => null];

        WebsiteGscDailyMetric::query()->updateOrCreate(
            ['website_id' => $website->id, 'date' => $date->toDateString()],
            [
                'clicks' => $totals['clicks'] ?? 0,
                'impressions' => $totals['impressions'] ?? 0,
                'ctr' => $totals['ctr'] ?? null,
                'position' => $totals['position'] ?? null,
            ],
        );

        return AnalyticsSyncLog::create([
            'source' => AnalyticsSyncLog::SOURCE_GSC,
            'website_id' => $website->id,
            'status' => AnalyticsSyncLog::STATUS_SUCCESS,
            'records_processed' => count($rows),
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }

    private function logFailure(Website $website, CarbonInterface $startedAt, string $message): AnalyticsSyncLog
    {
        return AnalyticsSyncLog::create([
            'source' => AnalyticsSyncLog::SOURCE_GSC,
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
        // sum_top_position is 0-indexed in the export; +1 gives the
        // conventional 1-indexed average position.
        return <<<SQL
            SELECT
              SUM(clicks) AS clicks,
              SUM(impressions) AS impressions,
              SAFE_DIVIDE(SUM(clicks), SUM(impressions)) AS ctr,
              SAFE_DIVIDE(SUM(sum_top_position), SUM(impressions)) + 1 AS position
            FROM `{$dataset}.searchdata_site_impression`
            WHERE data_date = @event_date
              AND site_url = @site_url
            SQL;
    }
}
