<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * Buckets an open task into exactly one actionable category, replacing a
 * flat "pending" count that told a manager nothing about what to actually
 * do next. Order matters: checked top to bottom, the first match wins — an
 * overdue-and-blocked task reads as overdue, the more urgent signal.
 */
class TaskStatusClassifier
{
    public const OVERDUE = 'overdue';

    public const DUE_TODAY = 'due_today';

    public const BLOCKED = 'blocked';

    public const AWAITING_APPROVAL = 'awaiting_approval';

    public const IN_PROGRESS = 'in_progress';

    public const PLANNED_LATER = 'planned_later';

    public const UNSCHEDULED_BACKLOG = 'unscheduled_backlog';

    public const ALL = [
        self::OVERDUE,
        self::DUE_TODAY,
        self::BLOCKED,
        self::AWAITING_APPROVAL,
        self::IN_PROGRESS,
        self::PLANNED_LATER,
        self::UNSCHEDULED_BACKLOG,
    ];

    public function classify(Task $task): string
    {
        if ($task->due_at !== null && $task->due_at->isPast()) {
            return self::OVERDUE;
        }

        if ($task->due_at !== null && $task->due_at->isToday()) {
            return self::DUE_TODAY;
        }

        $semanticStatus = $task->column?->semantic_status;

        if ($semanticStatus === 'blocked') {
            return self::BLOCKED;
        }

        if ($semanticStatus === 'review' || $task->approval_status === Task::APPROVAL_PENDING) {
            return self::AWAITING_APPROVAL;
        }

        if ($task->due_at !== null) {
            return self::PLANNED_LATER;
        }

        if (in_array($semanticStatus, ['active', 'ready'], true)) {
            return self::IN_PROGRESS;
        }

        return self::UNSCHEDULED_BACKLOG;
    }

    /**
     * @param  Builder<Task>  $openTasks  a query already scoped to "open" (not
     *                                    completed/archived) tasks — this only adds the classification, not the base filter
     * @return array<string, int> every bucket in ALL, zero-filled if empty
     */
    public function counts(Builder $openTasks): array
    {
        $counts = array_fill_keys(self::ALL, 0);

        $openTasks
            ->with('column:id,semantic_status')
            ->get(['id', 'due_at', 'approval_status', 'board_column_id'])
            ->each(function (Task $task) use (&$counts) {
                $counts[$this->classify($task)]++;
            });

        return $counts;
    }
}
