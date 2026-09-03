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

    public int $timeout = 60;

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

        // Where to drop it: the task's explicit choice (if it's still a live,
        // non-terminal column on this board), else the board's Ready column,
        // else the first column that isn't a completion/archive column — so a
        // board with no 'ready' semantic_status can't strand the task in a
        // done/archive lane (the "unchecks itself but never moves" bug).
        $chosenColumn = $task->auto_reset_column_id === null
            ? null
            : $task->board->columns()
                ->whereKey($task->auto_reset_column_id)
                ->where('is_completion_column', false)
                ->where('is_archive_column', false)
                ->first();

        $targetColumn = $chosenColumn
            ?? $task->board->columns()->where('semantic_status', 'ready')->first()
            ?? $task->board->columns()
                ->where('is_completion_column', false)
                ->where('is_archive_column', false)
                ->orderBy('position')
                ->first();

        if ($targetColumn === null) {
            return;
        }

        $readyColumn = $targetColumn;
        $alreadyThere = $task->board_column_id === $readyColumn->id;

        $resetItems = 0;

        $task = DB::transaction(function () use ($task, $readyColumn, $alreadyThere, &$resetItems): Task {
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

            AuditLogger::log($task, 'auto_renewed', [], [
                'checklist_items_reset' => $resetItems,
                'column_id' => $readyColumn->id,
                // Flags the "unchecked but didn't visibly move" case: the task
                // was already sitting in its reset column.
                'already_in_column' => $alreadyThere,
            ]);

            return $task;
        });

        if ($task->assignee?->wantsNotification('task_renewed')) {
            $task->assignee->notify(new TaskRenewed($task));
        }
    }
}
