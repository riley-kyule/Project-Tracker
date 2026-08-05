<?php

namespace App\Console\Commands;

use App\Jobs\ResetRecurringTask;
use App\Models\Task;
use App\Services\TaskAutoResetSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ResetRecurringTasks extends Command
{
    protected $signature = 'ewms:reset-recurring-tasks';

    protected $description = 'Dispatch a reset-to-Ready job for every recurring task whose daily/weekly/monthly period is due';

    public function handle(TaskAutoResetSchedule $schedule): int
    {
        $now = Carbon::now($schedule->timezone());
        $dispatched = 0;

        Task::query()
            ->whereNotNull('auto_reset_frequency')
            ->whereNull('archived_at')
            ->chunkById(200, function ($tasks) use ($schedule, $now, &$dispatched) {
                foreach ($tasks as $task) {
                    if ($schedule->isDue($task, $now)) {
                        ResetRecurringTask::dispatch($task->id);
                        $dispatched++;
                    }
                }
            });

        $this->info("Dispatched {$dispatched} recurring task reset(s).");

        return self::SUCCESS;
    }
}
