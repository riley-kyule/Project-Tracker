<?php

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;

/**
 * Every KPI in the Marketing Statistics module returns this same shape:
 * current value, comparison value, absolute change, percentage change,
 * which BigQuery-backed source it came from, and when that source's data
 * was last updated (so a stale source is visible per-KPI, not just
 * globally on the page).
 */
class KpiBuilder
{
    /** @return array{current: float|int|null, comparison: float|int|null, absolute_change: float|int|null, percentage_change: float|null, data_source: string, last_updated: string|null} */
    public static function build(
        float|int|null $current,
        float|int|null $comparison,
        string $dataSource,
        ?Carbon $lastUpdated,
    ): array {
        $absoluteChange = ($current !== null && $comparison !== null) ? $current - $comparison : null;
        $percentageChange = ($absoluteChange !== null && $comparison) ? $absoluteChange / $comparison : null;

        return [
            'current' => $current,
            'comparison' => $comparison,
            'absolute_change' => $absoluteChange,
            'percentage_change' => $percentageChange,
            'data_source' => $dataSource,
            'last_updated' => $lastUpdated?->toIso8601String(),
        ];
    }
}
