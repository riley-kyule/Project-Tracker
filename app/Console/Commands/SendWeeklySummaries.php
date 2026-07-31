<?php

namespace App\Console\Commands;

use App\Jobs\GenerateWeeklyReport;
use App\Models\CompanySetting;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendWeeklySummaries extends Command
{
    protected $signature = 'ewms:send-weekly-summaries';

    protected $description = 'Dispatch a personal weekly summary job for every active, opted-in employee once their week has ended';

    /** Week-in-review day: Friday, end of business. */
    private const WEEK_END_ISO_DAY = 5;

    private const WEEK_END_TIME = '16:00';

    public function handle(): int
    {
        $timezone = CompanySetting::current()->timezone ?: 'Africa/Nairobi';
        $now = Carbon::now($timezone);

        // This week's Friday is the report's anchor date regardless of which
        // day the job actually runs on — Fri 16:00 through the following Mon
        // 00:00 are all "due" for that same Friday, so a missed Friday run
        // still catches up over the weekend instead of skipping the week.
        $weekEndDay = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(self::WEEK_END_ISO_DAY - 1);

        $isDue = $now->dayOfWeekIso > self::WEEK_END_ISO_DAY
            || ($now->dayOfWeekIso === self::WEEK_END_ISO_DAY && $now->format('H:i') >= self::WEEK_END_TIME);

        if (! $isDue) {
            $this->info('Not yet due.');

            return self::SUCCESS;
        }

        $reportDate = $weekEndDay->toDateString();

        $alreadyGeneratedUserIds = ReportSnapshot::query()
            ->where('report_date', $reportDate)
            ->where('report_type', ReportSnapshot::TYPE_WEEKLY_PERSONAL)
            ->pluck('user_id');

        $dispatched = 0;

        User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotIn('id', $alreadyGeneratedUserIds)
            ->each(function (User $user) use ($reportDate, &$dispatched) {
                if (! $user->wantsNotification('weekly_summary')) {
                    return;
                }

                GenerateWeeklyReport::dispatch($user->id, $reportDate);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} weekly summary job(s).");

        return self::SUCCESS;
    }
}
