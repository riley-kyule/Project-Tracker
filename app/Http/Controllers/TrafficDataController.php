<?php

namespace App\Http\Controllers;

use App\Services\Analytics\TrafficDashboardQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class TrafficDataController extends Controller
{
    public function websites(Request $request, TrafficDashboardQuery $query): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        if (! $query->isConfigured()) {
            return response()->json(['configured' => false, 'websites' => []]);
        }

        try {
            return response()->json(['configured' => true, 'websites' => $query->mappedWebsites()]);
        } catch (Throwable $e) {
            return response()->json(['configured' => true, 'websites' => [], 'error' => $e->getMessage()], 502);
        }
    }

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

        $websiteDomain = $validated['website_domain'];
        $from = Carbon::parse($validated['date_from'])->startOfDay();
        $to = Carbon::parse($validated['date_to'])->startOfDay();
        $comparisonPeriod = $validated['comparison_period'] ?? 'none';

        try {
            $comparison = null;

            if ($comparisonPeriod !== 'none') {
                [$compareFrom, $compareTo] = $this->comparisonRange($from, $to, $comparisonPeriod);
                $comparison = $query->summary($websiteDomain, $compareFrom, $compareTo);
            }

            return response()->json([
                'configured' => true,
                'summary' => [
                    'current' => $query->summary($websiteDomain, $from, $to),
                    'comparison' => $comparison,
                ],
                'trend' => $query->dailyTrend($websiteDomain, $from, $to),
                'trafficSources' => $query->trafficSources($websiteDomain, $from, $to),
                'devices' => $query->devices($websiteDomain, $from, $to),
                'landingPages' => $query->landingPages($websiteDomain, $from, $to),
                'locations' => $query->locations($websiteDomain, $from, $to),
            ]);
        } catch (Throwable $e) {
            return response()->json(['configured' => true, 'error' => $e->getMessage()], 502);
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function comparisonRange(Carbon $from, Carbon $to, string $period): array
    {
        if ($period === 'previous_year') {
            return [$from->copy()->subYear(), $to->copy()->subYear()];
        }

        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();

        return [$previousTo->copy()->subDays($days - 1), $previousTo];
    }
}
