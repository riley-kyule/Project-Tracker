<?php

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;

/**
 * Shared by MarketingStatisticsController::freshness() (on-demand, per page
 * view) and the ewms:check-analytics-freshness command (scheduled, proactive
 * alerting) so the "is this source stale" logic exists in exactly one place.
 */
class AnalyticsFreshnessChecker
{
    public const STALE_AFTER_DAYS = 3;

    public function __construct(
        private readonly TrafficDashboardQuery $ga4,
        private readonly GscReportQuery $gsc,
        private readonly AhrefsReportQuery $ahrefs,
        private readonly AnalyticsReportBuilder $reportBuilder,
    ) {}

    /** @return array{ga4: array, gsc: array, ahrefs: array} */
    public function check(): array
    {
        $ga4Probe = $this->reportBuilder->attempt(
            fn () => $this->ga4->dailyRows(null, now()->subDays(self::STALE_AFTER_DAYS), now()->subDay()),
        );
        $gscFreshness = $this->reportBuilder->attempt(fn () => $this->gsc->freshness());
        $ahrefsProbe = $this->reportBuilder->attempt(fn () => $this->ahrefs->freshness());

        return [
            'ga4' => [
                'status' => $this->probeStatus($ga4Probe, 'event_date'),
                'error' => $ga4Probe['error'],
                'sites' => [],
            ],
            'gsc' => [
                'status' => $gscFreshness['status'] === 'failed' ? 'failed' : 'ok',
                'error' => $gscFreshness['error'],
                'sites' => array_map(fn (array $row) => [
                    'website_id' => $row['domain'],
                    'latest_date' => $row['latest_date'],
                    'days_behind' => $row['days_behind'],
                    'status' => $row['days_behind'] === null ? 'missing' : ($row['days_behind'] > self::STALE_AFTER_DAYS ? 'delayed' : 'ok'),
                ], $gscFreshness['rows']),
            ],
            'ahrefs' => [
                'status' => $ahrefsProbe['status'] === 'failed' ? 'missing' : 'ok',
                'error' => null, // Ahrefs has no pipeline yet — "missing" is the honest state, not a query error to surface.
                'sites' => [],
            ],
        ];
    }

    private function probeStatus(array $probe, string $dateKey): string
    {
        if ($probe['status'] === 'failed') {
            return 'failed';
        }

        if ($probe['rows'] === []) {
            return 'missing';
        }

        $latest = collect($probe['rows'])->max($dateKey);
        $daysBehind = Carbon::parse($latest)->diffInDays(now());

        return $daysBehind > self::STALE_AFTER_DAYS ? 'delayed' : 'ok';
    }
}
