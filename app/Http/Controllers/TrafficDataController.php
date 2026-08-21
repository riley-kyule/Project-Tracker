<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsCache;
use App\Services\Analytics\TrafficDashboardQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class TrafficDataController extends Controller
{
    public function __construct(private AnalyticsCache $cache) {}

    public function websites(Request $request, TrafficDashboardQuery $query): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        if (! $query->isConfigured()) {
            return response()->json(['configured' => false, 'websites' => []]);
        }

        try {
            $websites = $this->cache->remember(
                'ceo-traffic-websites', fn () => $query->mappedWebsites(), $request->boolean('refresh'),
            );

            return response()->json(['configured' => true, 'websites' => $websites]);
        } catch (Throwable $e) {
            return response()->json(['configured' => true, 'websites' => [], 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Was up to 7 sequential live BigQuery calls per request (summary ×2,
     * trend, 4 breakdowns) — the CEO dashboard's own "taking ages" report.
     * Cached as one unit like AnalyticsReportBuilder's report methods: the
     * whole payload behind a single key, only on success, so a transient
     * failure is never what gets cached for the rest of the day.
     */
    public function index(Request $request, TrafficDashboardQuery $query): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        if (! $query->isConfigured()) {
            return response()->json(['configured' => false]);
        }

        $validated = $request->validate([
            'website_domain' => ['required', 'string'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'comparison_period' => ['nullable', 'in:previous_period,previous_year,none'],
        ]);

        // 'all' is the site picker's "All Platforms" aggregate — null fans
        // out to every mapped website, same sentinel Marketing Statistics
        // uses for its own "All Sites" option (MarketingStatisticsFilters).
        $websiteDomain = $validated['website_domain'] === 'all' ? null : $validated['website_domain'];
        $from = Carbon::parse($validated['date_from'])->startOfDay();
        $to = Carbon::parse($validated['date_to'])->startOfDay();
        $comparisonPeriod = $validated['comparison_period'] ?? 'none';
        [$compareFrom, $compareTo] = $comparisonPeriod !== 'none' ? self::comparisonRange($from, $to, $comparisonPeriod) : [null, null];

        $key = AnalyticsCache::key('ceo-traffic', $websiteDomain, $from, $to, $compareFrom, $compareTo);

        try {
            $payload = $this->cache->remember($key, function () use ($query, $websiteDomain, $from, $to, $compareFrom, $compareTo) {
                return [
                    'configured' => true,
                    'summary' => [
                        'current' => $query->summary($websiteDomain, $from, $to),
                        'comparison' => $compareFrom !== null ? $query->summary($websiteDomain, $compareFrom, $compareTo) : null,
                    ],
                    'trend' => $query->dailyTrend($websiteDomain, $from, $to),
                    'trafficSources' => $query->trafficSources($websiteDomain, $from, $to),
                    'devices' => $query->devices($websiteDomain, $from, $to),
                    'landingPages' => $query->landingPages($websiteDomain, $from, $to),
                    'locations' => $query->locations($websiteDomain, $from, $to),
                ];
            }, $request->boolean('refresh'));

            return response()->json($payload);
        } catch (Throwable $e) {
            return response()->json(['configured' => true, 'error' => $e->getMessage()], 502);
        }
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
}
