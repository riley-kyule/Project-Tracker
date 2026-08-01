<?php

namespace App\Console\Commands;

use App\Jobs\GenerateWeeklyReport;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendWeeklySummaries extends Command
{
    protected $signature = 'ewms:send-weekly-summaries';

    protected $description = 'Dispatch each department\'s personal weekly summaries, and the CEO\'s company-wide one, once their configured time has passed';

    /** Week-in-review day: Friday, end of business. Only the time is configurable per department/CEO, same as the daily summaries. */
    private const WEEK_END_ISO_DAY = 5;

    public function handle(): int
    {
        $timezone = CompanySetting::current()->timezone ?: 'Africa/Nairobi';
        $now = Carbon::now($timezone);

        // This week's Friday is every report's anchor date regardless of
        // which day the job actually runs on — Friday's configured time
        // through the following Monday 00:00 are all "due" for that same
        // Friday, so a missed Friday run still catches up over the weekend
        // instead of skipping the week.
        $weekEndDay = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(self::WEEK_END_ISO_DAY - 1);
        $reportDate = $weekEndDay->toDateString();

        $dispatched = $this->dispatchPersonalReports($now, $reportDate);
        $dispatched += $this->dispatchCeoReport($now, $reportDate);

        $this->info("Dispatched {$dispatched} weekly summary job(s).");

        return self::SUCCESS;
    }

    private function dispatchPersonalReports(Carbon $now, string $reportDate): int
    {
        $dispatched = 0;

        Department::query()
            ->active()
            ->whereNotNull('weekly_summary_time')
            ->get()
            ->each(function (Department $department) use ($now, $reportDate, &$dispatched) {
                if (! $this->isDue($department->weekly_summary_time, $now)) {
                    return;
                }

                $memberIds = User::query()->where('department_id', $department->id)->pluck('id');

                $alreadyGeneratedUserIds = ReportSnapshot::query()
                    ->where('report_date', $reportDate)
                    ->where('report_type', ReportSnapshot::TYPE_WEEKLY_PERSONAL)
                    ->whereIn('user_id', $memberIds)
                    ->pluck('user_id');

                User::query()
                    ->where('department_id', $department->id)
                    ->where('status', User::STATUS_ACTIVE)
                    ->whereNotIn('id', $alreadyGeneratedUserIds)
                    ->each(function (User $user) use ($reportDate, &$dispatched) {
                        if (! $user->wantsNotification('weekly_summary')) {
                            return;
                        }

                        GenerateWeeklyReport::dispatch(ReportSnapshot::TYPE_WEEKLY_PERSONAL, $user->id, $reportDate);
                        $dispatched++;
                    });
            });

        return $dispatched;
    }

    private function dispatchCeoReport(Carbon $now, string $reportDate): int
    {
        if (! $this->isDue(CompanySetting::current()->ceo_weekly_summary_time, $now)) {
            return 0;
        }

        $alreadyGenerated = ReportSnapshot::query()
            ->where('report_date', $reportDate)
            ->where('report_type', ReportSnapshot::TYPE_CEO_WEEKLY)
            ->exists();

        if ($alreadyGenerated) {
            return 0;
        }

        GenerateWeeklyReport::dispatch(ReportSnapshot::TYPE_CEO_WEEKLY, null, $reportDate);

        return 1;
    }

    /** Due once Friday's configured time has passed; stays due through the weekend so a missed run still catches up. */
    private function isDue(?string $time, Carbon $now): bool
    {
        if ($time === null) {
            return false;
        }

        if ($now->dayOfWeekIso < self::WEEK_END_ISO_DAY) {
            return false;
        }

        if ($now->dayOfWeekIso > self::WEEK_END_ISO_DAY) {
            return true;
        }

        return $now->format('H:i') >= substr($time, 0, 5);
    }
}
