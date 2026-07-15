<?php

namespace App\Services;

use App\Models\BoardColumn;
use App\Models\Task;
use App\Notifications\TaskBlocked;
use Illuminate\Support\Facades\DB;

class TaskMover
{
    /**
     * Move a task to a column/position atomically, closing the gap it left
     * and opening one at the destination. Completion and archive timestamps
     * follow the semantic flags of the destination column.
     */
    public static function move(Task $task, BoardColumn $target, int $position): Task
    {
        $newlyCompleted = false;
        $enteringBlocked = false;

        $task = DB::transaction(function () use ($task, $target, $position, &$newlyCompleted, &$enteringBlocked) {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);

            $fromColumnId = $task->board_column_id;
            $fromPosition = $task->position;
            $newlyCompleted = $target->is_completion_column && $task->completed_at === null;
            $fromSemanticStatus = BoardColumn::query()->whereKey($fromColumnId)->value('semantic_status');
            $enteringBlocked = $target->semantic_status === 'blocked' && $fromSemanticStatus !== 'blocked';

            $maxPosition = (int) Task::query()
                ->where('board_column_id', $target->id)
                ->where('id', '!=', $task->id)
                ->max('position');
            $position = max(1, min($position, $maxPosition + 1));

            // Close the gap in the source column.
            Task::query()
                ->where('board_column_id', $fromColumnId)
                ->where('position', '>', $fromPosition)
                ->where('id', '!=', $task->id)
                ->decrement('position');

            // Open a gap in the destination column.
            Task::query()
                ->where('board_column_id', $target->id)
                ->where('position', '>=', $position)
                ->where('id', '!=', $task->id)
                ->increment('position');

            $task->forceFill([
                'board_column_id' => $target->id,
                'position' => $position,
                'completed_at' => $target->is_completion_column ? ($task->completed_at ?? now()) : null,
                'archived_at' => $target->is_archive_column ? ($task->archived_at ?? now()) : null,
            ])->save();

            if ($fromColumnId !== $target->id) {
                AuditLogger::log($task, 'moved', ['column_id' => $fromColumnId], ['column_id' => $target->id]);
            }

            return $task;
        });

        if ($newlyCompleted) {
            RecurrenceService::generateFromCompletion($task);
        }

        if ($enteringBlocked && $task->assignee?->wantsNotification('task_blocked')) {
            $task->assignee->notify(new TaskBlocked($task));
        }

        return $task;
    }
}
