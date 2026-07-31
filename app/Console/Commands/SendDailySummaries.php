<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailyReport;
use App\Models\ReportSnapshot;
use App\Services\Reports\DailySummarySchedule;
use Illuminate\Console\Command;

class SendDailySummaries extends Command
{
    protected $signature = 'ewms:send-daily-summaries';

    protected $description = 'Dispatch a report-generation job for the CEO summary and each department summary that is currently due';

    public function handle(DailySummarySchedule $schedule): int
    {
        $dispatched = 0;
        $businessDay = $schedule->businessDay()->toDateString();

        if ($schedule->isCeoSummaryDue()) {
            GenerateDailyReport::dispatch(ReportSnapshot::TYPE_CEO_DAILY, null, $businessDay);
            $dispatched++;
        }

        foreach ($schedule->dueDepartments() as $department) {
            GenerateDailyReport::dispatch(ReportSnapshot::TYPE_DEPARTMENT_DAILY, $department->id, $businessDay);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} daily report job(s).");

        return self::SUCCESS;
    }
}
