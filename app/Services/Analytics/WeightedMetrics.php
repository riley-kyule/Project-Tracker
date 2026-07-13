<?php

namespace App\Services\Analytics;

/**
 * Ratio metrics (engagement rate, CTR, average position) must never be
 * averaged row-by-row — a day with 10 sessions and a day with 10,000
 * sessions don't count equally. Every method here takes the additive
 * components BigQuery already summed per row (sessions, impressions,
 * clicks, etc.) and combines them by their weight, not a naive AVG().
 */
class WeightedMetrics
{
    /**
     * @param  array<int, array{sessions: int, engaged_sessions: int}>  $rows
     */
    public static function engagementRate(array $rows): ?float
    {
        $sessions = array_sum(array_column($rows, 'sessions'));

        if ($sessions <= 0) {
            return null;
        }

        return array_sum(array_column($rows, 'engaged_sessions')) / $sessions;
    }

    /**
     * @param  array<int, array{clicks: int, impressions: int}>  $rows
     */
    public static function ctr(array $rows): ?float
    {
        $impressions = array_sum(array_column($rows, 'impressions'));

        if ($impressions <= 0) {
            return null;
        }

        return array_sum(array_column($rows, 'clicks')) / $impressions;
    }

    /**
     * Each row's average_position is itself an average over that row's
     * impressions — combining rows requires weighting by impressions again,
     * not averaging the already-averaged positions.
     *
     * @param  array<int, array{impressions: int, average_position: float|null}>  $rows
     */
    public static function averagePosition(array $rows): ?float
    {
        $impressions = array_sum(array_column($rows, 'impressions'));

        if ($impressions <= 0) {
            return null;
        }

        $weighted = array_sum(array_map(
            fn (array $row) => ($row['average_position'] ?? 0) * $row['impressions'],
            $rows,
        ));

        return $weighted / $impressions;
    }

    /** @param array<int, int|float> $values */
    public static function sum(array $values): int|float
    {
        return array_sum($values);
    }
}
