<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AhrefsReportQuery;
use App\Services\Analytics\AnalyticsReportBuilder;
use App\Services\Analytics\GscReportQuery;
use App\Services\Analytics\MarketingStatisticsFilters;
use App\Services\Analytics\TrafficDashboardQuery;
use App\Services\Analytics\WebsiteRegistryQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Marketing Statistics module. Every action reads only from prepared
 * BigQuery reporting tables (GA4 `analytics_core.vw_*` views, GSC
 * `analytics_core.gsc_*` tables) — never raw GA4/GSC export data — per the
 * module spec. See ANALYTICS_BIGQUERY_FINDINGS.md for what's actually
 * queryable: GA4 and GSC are both confirmed working; Ahrefs has no
 * BigQuery pipeline yet. All three are treated as optional,
 * independently-failing sources so one gap never breaks the other two.
 *
 * `website_id` throughout this controller (query param, filter, frontend
 * prop) is the website's domain. GA4's own registry (`metadata.websites`)
 * has no separate ID column, only `dataset_id` + `website_domain` — domain
 * is the one field guaranteed to line up with GSC's tables too, so it's
 * used as the shared identifier rather than a GSC-specific one.
 */
class MarketingStatisticsController extends Controller
{
    private const STALE_AFTER_DAYS = 3;

    public function overview(
        Request $request, TrafficDashboardQuery $ga4, GscReportQuery $gsc, AhrefsReportQuery $ahrefs,
        WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder,
    ): Response {
        abort_unless($request->user()->can('view marketing statistics'), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $this->websiteRegistry($registryQuery, $reportBuilder);
        $domain = $filters->resolvedWebsiteId;

        $ga4Report = $reportBuilder->ga4Report($ga4, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo);
        $gscReport = $reportBuilder->gscReport($gsc, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo);
        $ahrefsReport = $reportBuilder->ahrefsReport($ahrefs, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo);

        return Inertia::render('marketing-statistics/overview', [
            'selected' => $filters->toArray(),
            'websites' => $registry,
            'sources' => [
                'ga4' => $reportBuilder->sourceSummary($ga4Report),
                'gsc' => $reportBuilder->sourceSummary($gscReport),
                'ahrefs' => $reportBuilder->sourceSummary($ahrefsReport),
            ],
            'ga4' => $ga4Report['kpis'],
            'gsc' => $gscReport['kpis'],
            'ahrefs' => $ahrefsReport['kpis'],
        ]);
    }

    public function ga4(Request $request, TrafficDashboardQuery $ga4, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder): Response
    {
        abort_unless($request->user()->can('view marketing statistics'), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $this->websiteRegistry($registryQuery, $reportBuilder);
        $domain = $filters->resolvedWebsiteId;

        $report = $reportBuilder->ga4Report($ga4, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo);
        $breakdowns = null;

        if ($report['status'] !== 'failed') {
            try {
                $breakdowns = [
                    'traffic_sources' => $ga4->trafficSources($domain, $filters->dateFrom, $filters->dateTo),
                    'devices' => $ga4->devices($domain, $filters->dateFrom, $filters->dateTo),
                    'landing_pages' => $ga4->landingPages($domain, $filters->dateFrom, $filters->dateTo),
                    'locations' => $ga4->locations($domain, $filters->dateFrom, $filters->dateTo),
                ];
            } catch (\Throwable) {
                // Summary succeeded but a breakdown query failed independently — the
                // page still renders with KPIs, just without these detail lists.
            }
        }

        return Inertia::render('marketing-statistics/ga4', [
            'selected' => $filters->toArray(),
            'websites' => $registry,
            'source' => $reportBuilder->sourceSummary($report),
            'kpis' => $report['kpis'],
            'trend' => $report['trend'],
            'breakdowns' => $breakdowns,
        ]);
    }

    public function gsc(Request $request, GscReportQuery $gsc, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder): Response
    {
        abort_unless($request->user()->can('view marketing statistics'), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $this->websiteRegistry($registryQuery, $reportBuilder);
        $domain = $filters->resolvedWebsiteId;

        $report = $reportBuilder->gscReport($gsc, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo);
        $breakdowns = null;

        if ($report['status'] !== 'failed') {
            try {
                $breakdowns = [
                    'queries' => $gsc->queries($domain, $filters->dateFrom, $filters->dateTo),
                    'pages' => $gsc->pages($domain, $filters->dateFrom, $filters->dateTo),
                    'countries' => $gsc->countries($domain, $filters->dateFrom, $filters->dateTo),
                    'devices' => $gsc->devices($domain, $filters->dateFrom, $filters->dateTo),
                ];
            } catch (\Throwable) {
                // Summary succeeded but a breakdown query failed independently.
            }
        }

        return Inertia::render('marketing-statistics/gsc', [
            'selected' => $filters->toArray(),
            'websites' => $registry,
            'source' => $reportBuilder->sourceSummary($report),
            'kpis' => $report['kpis'],
            'trend' => $report['trend'],
            'breakdowns' => $breakdowns,
        ]);
    }

    public function ahrefs(Request $request, AhrefsReportQuery $ahrefs, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder): Response
    {
        abort_unless($request->user()->can('view marketing statistics'), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $this->websiteRegistry($registryQuery, $reportBuilder);

        $report = $reportBuilder->ahrefsReport($ahrefs, $filters->resolvedWebsiteId, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo);

        return Inertia::render('marketing-statistics/ahrefs', [
            'selected' => $filters->toArray(),
            'websites' => $registry,
            'source' => $reportBuilder->sourceSummary($report),
            'kpis' => $report['kpis'],
            'trend' => $report['trend'],
        ]);
    }

    public function comparison(
        Request $request, TrafficDashboardQuery $ga4, GscReportQuery $gsc, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder,
    ): Response {
        abort_unless($request->user()->can('view marketing statistics'), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $this->websiteRegistry($registryQuery, $reportBuilder);

        $rows = array_map(function (array $site) use ($ga4, $gsc, $filters, $reportBuilder) {
            $ga4Report = $reportBuilder->ga4Report($ga4, $site['domain'], $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo);
            $gscReport = $reportBuilder->gscReport($gsc, $site['domain'], $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo);

            return [
                'website_id' => $site['website_id'],
                'name' => $site['name'],
                'domain' => $site['domain'],
                'ga4' => $ga4Report['status'] === 'ok' ? $ga4Report['kpis'] : null,
                'gsc' => $gscReport['status'] === 'ok' ? $gscReport['kpis'] : null,
            ];
        }, $registry);

        return Inertia::render('marketing-statistics/comparison', [
            'selected' => $filters->toArray(),
            'websites' => $registry,
            'rows' => $rows,
        ]);
    }

    public function freshness(
        Request $request, TrafficDashboardQuery $ga4, GscReportQuery $gsc, AhrefsReportQuery $ahrefs,
        WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder,
    ): Response {
        abort_unless($request->user()->can('view marketing statistics'), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $this->websiteRegistry($registryQuery, $reportBuilder);

        $ga4Probe = $reportBuilder->attempt(fn () => $ga4->dailyRows(null, now()->subDays(self::STALE_AFTER_DAYS), now()->subDay()));
        $gscFreshness = $reportBuilder->attempt(fn () => $gsc->freshness());
        $ahrefsProbe = $reportBuilder->attempt(fn () => $ahrefs->freshness());

        return Inertia::render('marketing-statistics/freshness', [
            'selected' => $filters->toArray(),
            'websites' => $registry,
            'sources' => [
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
            ],
        ]);
    }

    /** @return array<int, array{website_id: string, domain: string, name: string}> */
    private function websiteRegistry(WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder): array
    {
        $result = $reportBuilder->attempt(fn () => $registryQuery->websites());

        return array_map(fn (array $row) => [
            'website_id' => $row['domain'],
            'domain' => $row['domain'],
            'name' => $row['name'],
        ], $result['rows']);
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
