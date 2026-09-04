<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AhrefsReportQuery;
use App\Services\Analytics\AnalyticsFreshnessChecker;
use App\Services\Analytics\AnalyticsReportBuilder;
use App\Services\Analytics\GscReportQuery;
use App\Services\Analytics\MarketingStatisticsFilters;
use App\Services\Analytics\TrafficDashboardQuery;
use App\Services\Analytics\WebsiteRegistryQuery;
use Illuminate\Http\Request;
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
    public function overview(
        Request $request, TrafficDashboardQuery $ga4, GscReportQuery $gsc, AhrefsReportQuery $ahrefs,
        WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder,
    ): Response {
        abort_unless($request->user()->canViewMarketingStatistics(), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $reportBuilder->registry($registryQuery, $filters->forceRefresh);
        $domain = $filters->resolvedWebsiteId;

        $ga4Report = $reportBuilder->ga4Report(
            $ga4, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo, $filters->forceRefresh,
        );

        // GSC (and Ahrefs, when enabled) are each their own 1-2 query round
        // trip — deferred so the page paints with GA4's KPIs and headline
        // trend immediately, then the rest pops in. Each source's status +
        // KPIs are one deferred prop so the report is only computed once.
        $props = [
            'selected' => $filters->toArray(),
            'websites' => $registry,
            'ga4_source' => $reportBuilder->sourceSummary($ga4Report),
            'ga4' => $ga4Report['kpis'],
            'ga4_trend' => $ga4Report['trend'],
            'gsc' => Inertia::defer(function () use ($gsc, $domain, $filters, $reportBuilder) {
                $report = $reportBuilder->gscReport(
                    $gsc, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo, $filters->forceRefresh,
                );

                return ['source' => $reportBuilder->sourceSummary($report), 'kpis' => $report['kpis']];
            }, 'gsc'),
            'ahrefs_enabled' => config('analytics.bigquery.ahrefs_enabled'),
        ];

        if (config('analytics.bigquery.ahrefs_enabled')) {
            $props['ahrefs'] = Inertia::defer(function () use ($ahrefs, $domain, $filters, $reportBuilder) {
                $report = $reportBuilder->ahrefsReport(
                    $ahrefs, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo, $filters->forceRefresh,
                );

                return ['source' => $reportBuilder->sourceSummary($report), 'kpis' => $report['kpis']];
            }, 'ahrefs');
        }

        return Inertia::render('marketing-statistics/overview', $props);
    }

    public function ga4(Request $request, TrafficDashboardQuery $ga4, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder): Response
    {
        abort_unless($request->user()->canViewMarketingStatistics(), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $reportBuilder->registry($registryQuery, $filters->forceRefresh);
        $domain = $filters->resolvedWebsiteId;

        $report = $reportBuilder->ga4Report(
            $ga4, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo, $filters->forceRefresh,
        );

        // Each breakdown is a separate live scan over the GA4 UNION ALL view.
        // Deferred one-per-prop, each in its own group, so the browser fetches
        // them as parallel single-query requests — one request running all
        // five back to back routinely exceeded the web-server proxy timeout
        // and 502'd. The KPIs + headline trend above paint immediately.
        $breakdowns = [];
        foreach (AnalyticsReportBuilder::GA4_BREAKDOWNS as $name) {
            $breakdowns[$name] = Inertia::defer(
                fn () => $report['status'] === 'failed'
                    ? null
                    : $reportBuilder->ga4Breakdown($ga4, $name, $domain, $filters->dateFrom, $filters->dateTo, $filters->forceRefresh),
                "ga4-{$name}",
            );
        }

        return Inertia::render('marketing-statistics/ga4', [
            'selected' => $filters->toArray(),
            'ahrefs_enabled' => config('analytics.bigquery.ahrefs_enabled'),
            'websites' => $registry,
            'source' => $reportBuilder->sourceSummary($report),
            'kpis' => $report['kpis'],
            'trend' => $report['trend'],
            ...$breakdowns,
        ]);
    }

    public function gsc(Request $request, GscReportQuery $gsc, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder): Response
    {
        abort_unless($request->user()->canViewMarketingStatistics(), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $reportBuilder->registry($registryQuery, $filters->forceRefresh);
        $domain = $filters->resolvedWebsiteId;

        $report = $reportBuilder->gscReport(
            $gsc, $domain, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo, $filters->forceRefresh,
        );

        // Deferred one-per-prop in parallel groups, same as GA4's breakdowns.
        $breakdowns = [];
        foreach (AnalyticsReportBuilder::GSC_BREAKDOWNS as $name) {
            $breakdowns[$name] = Inertia::defer(
                fn () => $report['status'] === 'failed'
                    ? null
                    : $reportBuilder->gscBreakdown($gsc, $name, $domain, $filters->dateFrom, $filters->dateTo, $filters->forceRefresh),
                "gsc-{$name}",
            );
        }

        return Inertia::render('marketing-statistics/gsc', [
            'selected' => $filters->toArray(),
            'ahrefs_enabled' => config('analytics.bigquery.ahrefs_enabled'),
            'websites' => $registry,
            'source' => $reportBuilder->sourceSummary($report),
            'kpis' => $report['kpis'],
            'trend' => $report['trend'],
            ...$breakdowns,
        ]);
    }

    public function ahrefs(Request $request, AhrefsReportQuery $ahrefs, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder): Response
    {
        abort_unless($request->user()->canViewMarketingStatistics(), 403);
        abort_unless(config('analytics.bigquery.ahrefs_enabled'), 404);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $reportBuilder->registry($registryQuery, $filters->forceRefresh);

        $report = $reportBuilder->ahrefsReport(
            $ahrefs, $filters->resolvedWebsiteId, $filters->dateFrom, $filters->dateTo, $filters->compareFrom, $filters->compareTo, $filters->forceRefresh,
        );

        return Inertia::render('marketing-statistics/ahrefs', [
            'selected' => $filters->toArray(),
            'websites' => $registry,
            'source' => $reportBuilder->sourceSummary($report),
            'kpis' => $report['kpis'],
            'trend' => $report['trend'],
        ]);
    }

    /**
     * Two grouped BigQuery queries total, regardless of how many websites
     * are registered — see TrafficDashboardQuery::summaryByWebsite() and
     * GscReportQuery::summaryByWebsite(). The previous version called
     * ga4Report()/gscReport() once per website (up to 3 queries each),
     * which took minutes to render with ~20 real sites.
     */
    public function comparison(
        Request $request, TrafficDashboardQuery $ga4, GscReportQuery $gsc, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder,
    ): Response {
        abort_unless($request->user()->canViewMarketingStatistics(), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $reportBuilder->registry($registryQuery, $filters->forceRefresh);

        // Two grouped live-view scans across every registered site — deferred
        // so the tab paints its shell + a table skeleton immediately rather
        // than blocking first byte on both (which 502'd with ~20 sites).
        $comparison = Inertia::defer(
            fn () => $reportBuilder->websiteComparison($ga4, $gsc, $registry, $filters->dateFrom, $filters->dateTo, $filters->forceRefresh),
        );

        return Inertia::render('marketing-statistics/comparison', [
            'selected' => $filters->toArray(),
            'ahrefs_enabled' => config('analytics.bigquery.ahrefs_enabled'),
            'websites' => $registry,
            'comparison' => $comparison,
        ]);
    }

    public function freshness(
        Request $request, AnalyticsFreshnessChecker $freshnessChecker,
        WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder,
    ): Response {
        abort_unless($request->user()->canViewMarketingStatistics(), 403);

        $filters = MarketingStatisticsFilters::fromRequest($request);
        $registry = $reportBuilder->registry($registryQuery, $filters->forceRefresh);

        return Inertia::render('marketing-statistics/freshness', [
            'selected' => $filters->toArray(),
            'ahrefs_enabled' => config('analytics.bigquery.ahrefs_enabled'),
            'websites' => $registry,
            // Deliberately never cached, unlike every other source query in
            // this controller: the entire point of this tab is reporting
            // BigQuery's true current data recency, so serving a stale
            // cached "freshness" reading would be self-defeating. Three
            // independent BigQuery round-trips (GA4 probe, GSC freshness,
            // Ahrefs freshness) — deferred so the page paints immediately
            // instead of blocking on ~10s of sequential network calls, same
            // as the other Marketing Statistics tabs.
            'sources' => Inertia::defer(fn () => $freshnessChecker->check()),
        ]);
    }
}
