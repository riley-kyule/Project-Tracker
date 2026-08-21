<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsReportBuilder;
use App\Services\Analytics\GscReportQuery;
use App\Services\Analytics\TrafficDashboardQuery;
use App\Services\Analytics\WebsiteRegistryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The CEO dashboard's traffic widget — a snapshot built from the exact same
 * services and cache keys as the Marketing Statistics module
 * (AnalyticsReportBuilder, WebsiteRegistryQuery, GscReportQuery,
 * TrafficDashboardQuery), not a parallel bespoke query path. A CEO viewing
 * "All Platforms, last 30 days" here reuses whatever Marketing Statistics
 * (or the nightly warm job — see WarmAnalyticsCache) already computed for
 * that exact same view, instead of paying for it twice.
 */
class TrafficDataController extends Controller
{
    public function websites(Request $request, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        return response()->json(['websites' => $reportBuilder->registry($registryQuery, $request->boolean('refresh'))]);
    }

    /**
     * GA4 + GSC headline KPIs and trend — the fast half of the snapshot,
     * loaded first so the widget paints before the heavier breakdowns
     * below.
     */
    public function index(Request $request, TrafficDashboardQuery $ga4, GscReportQuery $gsc, AnalyticsReportBuilder $reportBuilder): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        [$domain, $from, $to, $compareFrom, $compareTo, $forceRefresh] = $this->parseFilters($request);

        $ga4Report = $reportBuilder->ga4Report($ga4, $domain, $from, $to, $compareFrom, $compareTo, $forceRefresh);
        $gscReport = $reportBuilder->gscReport($gsc, $domain, $from, $to, $compareFrom, $compareTo, $forceRefresh);

        return response()->json([
            'ga4' => ['source' => $reportBuilder->sourceSummary($ga4Report), 'kpis' => $ga4Report['kpis'], 'trend' => $ga4Report['trend']],
            'gsc' => ['source' => $reportBuilder->sourceSummary($gscReport), 'kpis' => $gscReport['kpis'], 'trend' => $gscReport['trend']],
        ]);
    }

    /**
     * Devices, landing pages, geography, key-event categories, GSC search
     * data, and the per-site comparison table — nine more BigQuery queries
     * beyond index() above, so the frontend fetches this separately and
     * shows it once it lands rather than blocking the headline KPIs on it.
     */
    public function breakdowns(
        Request $request, TrafficDashboardQuery $ga4, GscReportQuery $gsc, WebsiteRegistryQuery $registryQuery, AnalyticsReportBuilder $reportBuilder,
    ): JsonResponse {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        [$domain, $from, $to, , , $forceRefresh] = $this->parseFilters($request);

        $registry = $reportBuilder->registry($registryQuery, $forceRefresh);

        return response()->json([
            'ga4' => $reportBuilder->ga4Breakdowns($ga4, $domain, $from, $to, $forceRefresh),
            'gsc' => $reportBuilder->gscBreakdowns($gsc, $domain, $from, $to, $forceRefresh),
            'comparison' => $reportBuilder->websiteComparison($ga4, $gsc, $registry, $from, $to, $forceRefresh),
        ]);
    }

    /**
     * Public: also called by ewms:warm-analytics-cache to replicate this
     * dashboard's default comparison range exactly.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function comparisonRange(Carbon $from, Carbon $to, string $period): array
    {
        if ($period === 'previous_year') {
            return [$from->copy()->subYear(), $to->copy()->subYear()];
        }

        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();

        return [$previousTo->copy()->subDays($days - 1), $previousTo];
    }

    /** @return array{0: string|null, 1: Carbon, 2: Carbon, 3: Carbon|null, 4: Carbon|null, 5: bool} */
    private function parseFilters(Request $request): array
    {
        $validated = $request->validate([
            'website_domain' => ['required', 'string'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'comparison_period' => ['nullable', 'in:previous_period,previous_year,none'],
        ]);

        // 'all' is the site picker's "All Platforms" aggregate — null fans
        // out to every mapped website, same sentinel Marketing Statistics
        // uses for its own "All Sites" option (MarketingStatisticsFilters).
        $domain = $validated['website_domain'] === 'all' ? null : $validated['website_domain'];
        $from = Carbon::parse($validated['date_from'])->startOfDay();
        $to = Carbon::parse($validated['date_to'])->startOfDay();
        $comparisonPeriod = $validated['comparison_period'] ?? 'none';
        [$compareFrom, $compareTo] = $comparisonPeriod !== 'none' ? self::comparisonRange($from, $to, $comparisonPeriod) : [null, null];

        return [$domain, $from, $to, $compareFrom, $compareTo, $request->boolean('refresh')];
    }
}
