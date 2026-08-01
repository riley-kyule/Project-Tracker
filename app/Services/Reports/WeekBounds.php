<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;

/** Shared by WeeklyPersonalSummaryBuilder and CeoWeeklySummaryBuilder so "the week ending on this Friday" means exactly the same thing in both. */
class WeekBounds
{
    /**
     * @return array{0: Carbon, 1: Carbon} Monday 00:00 (inclusive) through the
     *                                     Saturday immediately after $weekEndDay (exclusive), in UTC
     */
    public static function forWeekEndingOn(Carbon $weekEndDay, string $timezone): array
    {
        $localWeekEndDay = Carbon::parse($weekEndDay->toDateString(), $timezone)->startOfDay();
        $end = $localWeekEndDay->copy()->addDay()->utc();
        $start = $localWeekEndDay->copy()->subDays(4)->utc();

        return [$start, $end];
    }
}
