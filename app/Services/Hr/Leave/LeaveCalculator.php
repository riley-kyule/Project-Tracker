<?php

namespace App\Services\Hr\Leave;

use App\Models\PublicHoliday;
use Illuminate\Support\Carbon;

/**
 * Turns a date range into a working-day count: weekends and public holidays
 * are excluded, and a half day at either end subtracts 0.5.
 */
class LeaveCalculator
{
    public function workingDays(Carbon $start, Carbon $end, bool $halfStart = false, bool $halfEnd = false): float
    {
        if ($end->lt($start)) {
            return 0.0;
        }

        $holidays = PublicHoliday::datesBetween($start, $end);
        $days = 0.0;

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if ($cursor->isWeekend() || in_array($cursor->toDateString(), $holidays, true)) {
                continue;
            }
            $days += 1;
        }

        if ($days === 0.0) {
            return 0.0;
        }

        // Half-day flags only bite when the boundary day is itself a working day.
        if ($halfStart && ! $start->isWeekend() && ! in_array($start->toDateString(), $holidays, true)) {
            $days -= 0.5;
        }

        if ($halfEnd && ! $end->isWeekend() && ! in_array($end->toDateString(), $holidays, true) && ! $start->isSameDay($end)) {
            $days -= 0.5;
        }

        return max($days, 0.0);
    }
}
