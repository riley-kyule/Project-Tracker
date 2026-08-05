<?php

namespace App\Services\Reports;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\ReportSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Decides whether a daily summary is due right now, against the company's
 * configured timezone rather than the app's UTC clock. "Due" means the
 * configured send time has passed today and no report_snapshots row for
 * today already exists — that row, not a last_sent_on column checked
 * against a narrow polling window, is the source of truth for "already
 * sent today", so this stays correct regardless of how often the
 * scheduler actually runs.
 */
class DailySummarySchedule
{
    public function timezone(): string
    {
        return CompanySetting::current()->timezone ?: 'Africa/Nairobi';
    }

    public function businessDay(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    public function isCeoSummaryDue(): bool
    {
        // The CEO daily is a rollup of department activity — skipping it on
        // the same day departments skip by default keeps it from arriving
        // as a near-empty email nobody asked for.
        if ($this->businessDay()->isSunday()) {
            return false;
        }

        return $this->isDue(CompanySetting::current()->ceo_summary_time, ReportSnapshot::TYPE_CEO_DAILY, null);
    }

    /** @return Collection<int, Department> */
    public function dueDepartments(): Collection
    {
        $isSunday = $this->businessDay()->isSunday();

        return Department::query()
            ->active()
            ->whereNotNull('daily_summary_time')
            ->when($isSunday, fn ($query) => $query->where('send_sunday_reports', true))
            ->get()
            ->filter(fn (Department $department) => $this->isDue(
                $department->daily_summary_time,
                ReportSnapshot::TYPE_DEPARTMENT_DAILY,
                $department->id,
            ))
            ->values();
    }

    private function isDue(?string $time, string $reportType, ?int $departmentId): bool
    {
        if ($time === null) {
            return false;
        }

        $now = $this->businessDay();
        $configured = $now->copy()->setTimeFromTimeString($time);

        if ($now->lt($configured)) {
            return false;
        }

        return ! ReportSnapshot::query()
            ->where('report_date', $now->toDateString())
            ->where('report_type', $reportType)
            ->where('department_id', $departmentId)
            ->exists();
    }
}
