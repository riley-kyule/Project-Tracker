<?php

namespace App\Services\Seo;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use Illuminate\Support\Carbon;

/**
 * Whether a given date is a day an employee is actually expected to work —
 * SEO Board Requirements Specification v1.1 §4.2/§10: approved leave,
 * non-working days and company holidays must be excluded so EWMS never
 * manufactures a false underperformance record, and the midnight lifecycle
 * must skip straight to "the next applicable workday" rather than the
 * calendar's next date.
 */
class WorkCalendarService
{
    /** ISO weekdays (1 = Monday ... 7 = Sunday), same encoding as CompanySetting::business_hours_days. */
    private const DEFAULT_WORK_DAYS = [1, 2, 3, 4, 5];

    public function isWorkingDay(Employee $employee, Carbon $date): bool
    {
        $workDays = CompanySetting::current()->business_hours_days ?: self::DEFAULT_WORK_DAYS;

        if (! in_array($date->dayOfWeekIso, $workDays, true)) {
            return false;
        }

        if (PublicHoliday::datesBetween($date->copy()->startOfDay(), $date->copy()->endOfDay()) !== []) {
            return false;
        }

        return ! $this->isOnApprovedLeave($employee, $date);
    }

    public function isOnApprovedLeave(Employee $employee, Carbon $date): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->overlapping($date->toDateString(), $date->toDateString())
            ->exists();
    }

    /** The next date on/after $from that $employee is actually expected to work, scanning forward at most one year. */
    public function nextWorkingDay(Employee $employee, Carbon $from): Carbon
    {
        $date = $from->copy()->startOfDay();
        $limit = $from->copy()->addYear();

        while ($date->lt($limit) && ! $this->isWorkingDay($employee, $date)) {
            $date = $date->addDay();
        }

        return $date;
    }

    /** Working days for $employee within [$from, $to], inclusive — used to size the daily-average denominator (§2/§4.2). */
    public function workingDaysBetween(Employee $employee, Carbon $from, Carbon $to): int
    {
        $count = 0;
        $date = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        while ($date->lte($to)) {
            if ($this->isWorkingDay($employee, $date)) {
                $count++;
            }
            $date = $date->addDay();
        }

        return $count;
    }
}
