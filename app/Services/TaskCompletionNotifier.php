<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskCompletedCeo;
use App\Notifications\TaskCompletedDepartmentHead;

/**
 * Real-time, in-app only (database channel) — the once-daily rollup email
 * to the CEO and department heads is a separate digest (SendDailySummaries),
 * not repeated here per-task to avoid doubling up on noise.
 */
class TaskCompletionNotifier
{
    public static function notify(Task $task): void
    {
        User::role('CEO')->get()->each(function (User $ceo) use ($task) {
            if ($ceo->wantsNotification('task_completed_ceo')) {
                $ceo->notify(new TaskCompletedCeo($task));
            }
        });

        $department = $task->department;

        if ($department === null) {
            return;
        }

        collect([$department->manager, $department->assistantManager])
            ->filter()
            ->unique('id')
            ->each(function (User $head) use ($task) {
                if ($head->wantsNotification('task_completed_department')) {
                    $head->notify(new TaskCompletedDepartmentHead($task));
                }
            });
    }
}
