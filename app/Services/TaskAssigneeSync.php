<?php

namespace App\Services;

use App\Models\Task;

class TaskAssigneeSync
{
    /**
     * Keep task_assignees in sync with tasks.primary_assignee_id, per the
     * invariant in DATABASE_SCHEMA.md: whenever the primary assignee is set,
     * that user must exist here with assignment_type "assignee", and no
     * stale "assignee" row should be left for whoever held it before.
     */
    public static function syncPrimary(Task $task, ?int $previousAssigneeId): void
    {
        if ($previousAssigneeId !== null && $previousAssigneeId !== $task->primary_assignee_id) {
            $task->assignees()->wherePivot('assignment_type', 'assignee')->detach($previousAssigneeId);
        }

        if ($task->primary_assignee_id !== null) {
            $task->assignees()->syncWithoutDetaching([
                $task->primary_assignee_id => ['assignment_type' => 'assignee'],
            ]);
        }
    }
}
