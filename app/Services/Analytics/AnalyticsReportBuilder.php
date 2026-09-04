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
    public function __construct(private AnalyticsCache $cache) {}

    public function ga4Report(
        TrafficDashboardQuery $ga4, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo,
        ?Carbon $compareFrom = null, ?Carbon $compareTo = null, bool $forceRefresh = false,
    ): array {
        $key = AnalyticsCache::key('ga4', $domain, $dateFrom, $dateTo, $compareFrom, $compareTo);

        try {
            // Cached inside the try, not around this whole method: a cache
            // hit or a fresh success both return normally and get (re-)cached
            // for the day, but a thrown exception here never gets cached —
            // it falls straight to the catch below, uncached, so the next
            // request retries live instead of a transient failure sticking
            // around for the rest of the day.
            return $this->cache->remember($key, function () use ($ga4, $domain, $dateFrom, $dateTo, $compareFrom, $compareTo) {
                $rows = $ga4->dailyRows($domain, $dateFrom, $dateTo);
                $keyEvents = $ga4->keyEventsTotal($domain, $dateFrom, $dateTo);

                $hasComparison = $compareFrom !== null && $compareTo !== null;
                $compareRows = [];
                $compareKeyEvents = null;

                if ($hasComparison) {
                    $compareRows = $ga4->dailyRows($domain, $compareFrom, $compareTo);
                    $compareKeyEvents = $ga4->keyEventsTotal($domain, $compareFrom, $compareTo);
                }

                // Frozen at the moment this was actually fetched (and cached),
                // not re-evaluated to "now" on every cache hit — this is what
                // makes the KPI's displayed "last updated" mean something real.
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
            }, $forceRefresh);
        } catch (Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage(), 'kpis' => null, 'trend' => []];
        }
    }

    public function gscReport(
        GscReportQuery $gsc, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo,
        ?Carbon $compareFrom = null, ?Carbon $compareTo = null, bool $forceRefresh = false,
    ): array {
        $key = AnalyticsCache::key('gsc', $domain, $dateFrom, $dateTo, $compareFrom, $compareTo);

        try {
            return $this->cache->remember($key, function () use ($gsc, $domain, $dateFrom, $dateTo, $compareFrom, $compareTo) {
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
            }, $forceRefresh);
        } catch (Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage(), 'kpis' => null, 'trend' => []];
        }
    }

    public function ahrefsReport(
        AhrefsReportQuery $ahrefs, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo,
        ?Carbon $compareFrom = null, ?Carbon $compareTo = null, bool $forceRefresh = false,
    ): array {
        $key = AnalyticsCache::key('ahrefs', $domain, $dateFrom, $dateTo, $compareFrom, $compareTo);

        try {
            return $this->cache->remember($key, function () use ($ahrefs, $domain, $dateFrom, $dateTo, $compareFrom, $compareTo) {
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
            }, $forceRefresh);
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

    /**
     * The registry every report below is keyed against — shared by every
     * caller (Marketing Statistics' own controller and anything else that
     * needs "the list of mapped websites") so they all read/warm the exact
     * same 'registry' cache entry instead of each keeping their own copy.
     *
     * @return array<int, array{website_id: string, domain: string, name: string}>
     */
    public function registry(WebsiteRegistryQuery $registryQuery, bool $forceRefresh = false): array
    {
        $result = $this->attempt(fn () => $this->cache->remember('registry', fn () => $registryQuery->websites(), $forceRefresh));

        return array_map(fn (array $row) => [
            'website_id' => $row['domain'],
            'domain' => $row['domain'],
            'name' => $row['name'],
        ], $result['rows']);
    }

    /**
     * The GA4 / GSC "breakdown" queries beyond ga4Report()/gscReport() —
     * each is its own single BigQuery query, cached and try/catch-isolated
     * on its own key, returning null on failure (callers render each
     * conditionally). One-per-key rather than one combined result so the
     * controller can hand each to Inertia::defer() in its own group: the
     * browser then fetches all of them as parallel single-query requests
     * instead of one request running 4-5 live-view scans back to back
     * (which routinely blew past the web-server proxy timeout → 502).
     *
     * @param  'traffic_sources'|'devices'|'landing_pages'|'locations'|'key_events'  $name
     */
    public function ga4Breakdown(
        TrafficDashboardQuery $ga4, string $name, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo, bool $forceRefresh = false,
    ): ?array {
        try {
            return $this->cache->remember(
                AnalyticsCache::key("ga4-bd-{$name}", $domain, $dateFrom, $dateTo),
                fn () => match ($name) {
                    'traffic_sources' => $ga4->trafficSources($domain, $dateFrom, $dateTo),
                    'devices' => $ga4->devices($domain, $dateFrom, $dateTo),
                    'landing_pages' => $ga4->landingPages($domain, $dateFrom, $dateTo),
                    'locations' => $ga4->locations($domain, $dateFrom, $dateTo),
                    'key_events' => $ga4->keyEventsBreakdown($domain, $dateFrom, $dateTo),
                },
                $forceRefresh,
            );
        } catch (Throwable) {
            return null;
        }
    }

    /** @param  'queries'|'pages'|'countries'|'devices'  $name */
    public function gscBreakdown(
        GscReportQuery $gsc, string $name, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo, bool $forceRefresh = false,
    ): ?array {
        try {
            return $this->cache->remember(
                AnalyticsCache::key("gsc-bd-{$name}", $domain, $dateFrom, $dateTo),
                fn () => match ($name) {
                    'queries' => $gsc->queries($domain, $dateFrom, $dateTo),
                    'pages' => $gsc->pages($domain, $dateFrom, $dateTo),
                    'countries' => $gsc->countries($domain, $dateFrom, $dateTo),
                    'devices' => $gsc->devices($domain, $dateFrom, $dateTo),
                },
                $forceRefresh,
            );
        } catch (Throwable) {
            return null;
        }
    }

    public const GA4_BREAKDOWNS = ['traffic_sources', 'devices', 'landing_pages', 'locations', 'key_events'];

    public const GSC_BREAKDOWNS = ['queries', 'pages', 'countries', 'devices'];

    /**
     * All GA4 breakdowns as one dict — for callers that render them together
     * in a single deferred blob (the CEO dashboard traffic widget). Each part
     * is still cached on its own key (see ga4Breakdown()), so this shares
     * cache with the Marketing Statistics module's per-part fetches.
     */
    public function ga4Breakdowns(TrafficDashboardQuery $ga4, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo, bool $forceRefresh = false): ?array
    {
        $out = [];
        foreach (self::GA4_BREAKDOWNS as $name) {
            $out[$name] = $this->ga4Breakdown($ga4, $name, $domain, $dateFrom, $dateTo, $forceRefresh) ?? [];
        }

        return $out;
    }

    public function gscBreakdowns(GscReportQuery $gsc, string|array|null $domain, Carbon $dateFrom, Carbon $dateTo, bool $forceRefresh = false): ?array
    {
        $out = [];
        foreach (self::GSC_BREAKDOWNS as $name) {
            $out[$name] = $this->gscBreakdown($gsc, $name, $domain, $dateFrom, $dateTo, $forceRefresh) ?? [];
        }

        return $out;
    }

    /**
     * One grouped GA4 query + one grouped GSC query across every registered
     * website — a fixed cost regardless of site count (see
     * TrafficDashboardQuery::summaryByWebsite() / GscReportQuery::summaryByWebsite()).
     *
     * @param  array<int, array{website_id: string, domain: string, name: string}>  $registry
     * @return array{rows: array, sources: array{ga4: array, gsc: array}}
     */
    public function websiteComparison(TrafficDashboardQuery $ga4, GscReportQuery $gsc, array $registry, Carbon $dateFrom, Carbon $dateTo, bool $forceRefresh = false): array
    {
        $domains = array_column($registry, 'domain');
        $ga4Key = AnalyticsCache::key('comparison-ga4', $domains, $dateFrom, $dateTo);
        $gscKey = AnalyticsCache::key('comparison-gsc', $domains, $dateFrom, $dateTo);

        $ga4Summary = $this->attempt(
            fn () => $this->cache->remember($ga4Key, fn () => $ga4->summaryByWebsite($domains, $dateFrom, $dateTo), $forceRefresh),
        );
        $gscSummary = $this->attempt(
            fn () => $this->cache->remember($gscKey, fn () => $gsc->summaryByWebsite($domains, $dateFrom, $dateTo), $forceRefresh),
        );

        $rows = array_map(fn (array $site) => [
            'website_id' => $site['website_id'],
            'name' => $site['name'],
            'domain' => $site['domain'],
            'ga4' => $ga4Summary['rows'][$site['domain']] ?? null,
            'gsc' => $gscSummary['rows'][$site['domain']] ?? null,
        ], $registry);

        return ['rows' => $rows, 'sources' => ['ga4' => $this->sourceSummary($ga4Summary), 'gsc' => $this->sourceSummary($gscSummary)]];
    }
}
