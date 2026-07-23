<?php

namespace App\Services;

use App\Models\Task;

/**
 * Keeps a task's progress_percentage in sync with how many of its checklist
 * items are marked done, whenever it has any. Tasks with no checklist items
 * keep their manually-set progress untouched — there's nothing to derive it
 * from.
 */
class TaskChecklistProgress
{
    public static function sync(Task $task): void
    {
        $total = $task->checklistItems()->count();

        if ($total === 0) {
            return;
        }

        $completed = $task->checklistItems()->where('is_completed', true)->count();
        $percentage = (int) round($completed / $total * 100);

        $task->update(['progress_percentage' => $percentage]);

        if ($percentage === 100) {
            TaskMover::moveToCompletionColumnIfReady($task);
        }
    }
}
