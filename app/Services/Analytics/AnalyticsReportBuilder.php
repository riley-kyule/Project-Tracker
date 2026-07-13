<?php

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Shared GA4/GSC/Ahrefs KPI computation — used by the Marketing Statistics
 * module (a single domain or "all sites") and the per-member scoped "My
 * Reports" flow (an arbitrary subset of assigned domains). Only the shape
 * of $domain differs (string|array|null); the weighted-metric math and
 * per-source failure isolation are identical, so both live here once.
 */
class AnalyticsReportBuilder
{
    public function ga4Report(
        TrafficDashboardQuery $ga4, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo,
        ?Carbon $compareFrom = null, ?Carbon $compareTo = null,
    ): array {
        try {
            $rows = $ga4->dailyRows($domain, $dateFrom, $dateTo);
            $keyEvents = $ga4->keyEventsTotal($domain, $dateFrom, $dateTo);

            $hasComparison = $compareFrom !== null && $compareTo !== null;
            $compareRows = [];
            $compareKeyEvents = null;

            if ($hasComparison) {
                $compareRows = $ga4->dailyRows($domain, $compareFrom, $compareTo);
                $compareKeyEvents = $ga4->keyEventsTotal($domain, $compareFrom, $compareTo);
            }

            $lastUpdated = now();

            return [
                'status' => empty($rows) ? 'missing' : 'ok',
                'error' => null,
                'trend' => $rows,
                'kpis' => [
                    // Never "unique users" — user_pseudo_id is property-scoped; summing
                    // across properties (All Sites) just adds each property's own count.
                    'aggregate_property_users' => KpiBuilder::build(
                        WeightedMetrics::sum(array_column($rows, 'users')),
                        $hasComparison ? WeightedMetrics::sum(array_column($compareRows, 'users')) : null,
                        'ga4', $lastUpdated,
                    ),
                    'sessions' => KpiBuilder::build(
                        WeightedMetrics::sum(array_column($rows, 'sessions')),
                        $hasComparison ? WeightedMetrics::sum(array_column($compareRows, 'sessions')) : null,
                        'ga4', $lastUpdated,
                    ),
                    'key_events' => KpiBuilder::build($keyEvents, $compareKeyEvents, 'ga4', $lastUpdated),
                    'engagement_rate' => KpiBuilder::build(
                        WeightedMetrics::engagementRate($rows),
                        $hasComparison ? WeightedMetrics::engagementRate($compareRows) : null,
                        'ga4', $lastUpdated,
                    ),
                ],
            ];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage(), 'kpis' => null, 'trend' => []];
        }
    }

    public function gscReport(
        GscReportQuery $gsc, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo,
        ?Carbon $compareFrom = null, ?Carbon $compareTo = null,
    ): array {
        try {
            $hasComparison = $compareFrom !== null && $compareTo !== null;
            $rows = $gsc->dailyRows($domain, $dateFrom, $dateTo);
            $compareRows = $hasComparison ? $gsc->dailyRows($domain, $compareFrom, $compareTo) : [];
            $lastUpdated = now();

            return [
                'status' => empty($rows) ? 'missing' : 'ok',
                'error' => null,
                'trend' => $rows,
                'kpis' => [
                    'clicks' => KpiBuilder::build(
                        WeightedMetrics::sum(array_column($rows, 'clicks')),
                        $hasComparison ? WeightedMetrics::sum(array_column($compareRows, 'clicks')) : null,
                        'gsc', $lastUpdated,
                    ),
                    'impressions' => KpiBuilder::build(
                        WeightedMetrics::sum(array_column($rows, 'impressions')),
                        $hasComparison ? WeightedMetrics::sum(array_column($compareRows, 'impressions')) : null,
                        'gsc', $lastUpdated,
                    ),
                    'ctr' => KpiBuilder::build(
                        WeightedMetrics::ctr($rows),
                        $hasComparison ? WeightedMetrics::ctr($compareRows) : null,
                        'gsc', $lastUpdated,
                    ),
                    'average_position' => KpiBuilder::build(
                        WeightedMetrics::averagePosition($rows),
                        $hasComparison ? WeightedMetrics::averagePosition($compareRows) : null,
                        'gsc', $lastUpdated,
                    ),
                ],
            ];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage(), 'kpis' => null, 'trend' => []];
        }
    }

    public function ahrefsReport(
        AhrefsReportQuery $ahrefs, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo,
        ?Carbon $compareFrom = null, ?Carbon $compareTo = null,
    ): array {
        try {
            $hasComparison = $compareFrom !== null && $compareTo !== null;
            $rows = $ahrefs->dailyRows($domain, $dateFrom, $dateTo);
            $compareRows = $hasComparison ? $ahrefs->dailyRows($domain, $compareFrom, $compareTo) : [];
            $lastUpdated = now();

            // domain_rating/backlinks/referring_domains/organic_keywords/estimated_traffic
            // are point-in-time snapshots — use the most recent day in range, not a sum.
            $latest = $rows === [] ? null : $rows[array_key_last($rows)];
            $latestCompare = $compareRows === [] ? null : $compareRows[array_key_last($compareRows)];

            $snapshot = fn (string $key) => KpiBuilder::build($latest[$key] ?? null, $latestCompare[$key] ?? null, 'ahrefs', $lastUpdated);
            $period = fn (string $key) => KpiBuilder::build(
                WeightedMetrics::sum(array_column($rows, $key)),
                $hasComparison ? WeightedMetrics::sum(array_column($compareRows, $key)) : null,
                'ahrefs', $lastUpdated,
            );

            return [
                'status' => empty($rows) ? 'missing' : 'ok',
                'error' => null,
                'trend' => $rows,
                'kpis' => [
                    'domain_rating' => $snapshot('domain_rating'),
                    'backlinks' => $snapshot('backlinks'),
                    'referring_domains' => $snapshot('referring_domains'),
                    'organic_keywords' => $snapshot('organic_keywords'),
                    'estimated_organic_traffic' => $snapshot('estimated_traffic'),
                    'new_backlinks' => $period('new_backlinks'),
                    'lost_backlinks' => $period('lost_backlinks'),
                    'keyword_gains' => $period('keyword_gains'),
                    'keyword_losses' => $period('keyword_losses'),
                ],
            ];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage(), 'kpis' => null, 'trend' => []];
        }
    }

    /** @return array{status: string, error: string|null, rows: array} */
    public function attempt(callable $fn): array
    {
        try {
            $rows = $fn();

            return ['status' => empty($rows) ? 'missing' : 'ok', 'error' => null, 'rows' => $rows];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage(), 'rows' => []];
        }
    }

    /** @return array{status: string, error: string|null} */
    public function sourceSummary(array $report): array
    {
        return ['status' => $report['status'], 'error' => $report['error']];
    }
}
