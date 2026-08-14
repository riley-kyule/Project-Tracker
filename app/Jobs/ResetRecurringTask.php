<?php

namespace App\Jobs;

use App\Models\Task;
use App\Notifications\TaskRenewed;
use App\Services\AuditLogger;
use App\Services\TaskAutoResetSchedule;
use App\Services\TaskMover;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** One job per task — mirrors GenerateDailyReport/GenerateWeeklyReport's one-job-per-unit shape, so one board's misconfiguration can't block another task's reset. */
class ResetRecurringTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $taskId) {}

    public function handle(TaskAutoResetSchedule $schedule): void
    {
        $task = Task::find($this->taskId);

        if ($task === null) {
            return;
        }

        $timezone = $schedule->timezone();

        // Re-verify due-ness: the command's sweep and this job's execution
        // aren't atomic, so a task could already have been reset (or its
        // recurrence turned off) between being queued and running.
        if (! $schedule->isDue($task, Carbon::now($timezone))) {
            return;
        }

        $readyColumn = $task->board->columns()->where('semantic_status', 'ready')->first()
            ?? $task->board->columns()->orderBy('position')->first();

        if ($readyColumn === null) {
            return;
        }

        $resetItems = 0;

        $task = DB::transaction(function () use ($task, $readyColumn, &$resetItems): Task {
            $position = (int) $readyColumn->tasks()->max('position') + 1;
            $task = TaskMover::move($task, $readyColumn, $position);

            $checklistItems = $task->checklistItems();
            $hasChecklistItems = $checklistItems->exists();
            $resetItems = $checklistItems
                ->where(function ($query) {
                    $query->where('is_completed', true)
                        ->orWhereNotNull('completed_by')
                        ->orWhereNotNull('completed_at');
                })
                ->update([
                    'is_completed' => false,
                    'completed_by' => null,
                    'completed_at' => null,
                ]);

            $task->forceFill([
                'last_auto_reset_at' => now(),
                'progress_percentage' => $hasChecklistItems ? 0 : $task->progress_percentage,
            ])->save();

            AuditLogger::log($task, 'auto_renewed', [], ['checklist_items_reset' => $resetItems]);

            return $task;
        });

        if ($task->assignee?->wantsNotification('task_renewed')) {
            $task->assignee->notify(new TaskRenewed($task));
        }
    }
}
