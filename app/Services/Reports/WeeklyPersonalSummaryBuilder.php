<?php

namespace App\Services\Reports;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskStatusClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds one employee's personal weekly summary: what they completed this
 * week and where their open work currently stands. Reuses the same
 * completion-note-over-title preference and bucketed pending breakdown as
 * the daily department summary, just scoped to one person instead of a
 * department.
 */
class WeeklyPersonalSummaryBuilder
{
    public function __construct(private readonly TaskStatusClassifier $classifier = new TaskStatusClassifier) {}

    /** @return array{completed_count: int, completed: Collection<int, array{label: string, url: string, description: ?string, checklist_progress: ?string}>, pending_breakdown: array<string, int>} */
    public function build(User $user, Carbon $weekEndDay, string $timezone): array
    {
        [$start, $end] = WeekBounds::forWeekEndingOn($weekEndDay, $timezone);

        $completed = Task::query()
            ->where('primary_assignee_id', $user->id)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->withCount(['checklistItems', 'checklistItems as completed_checklist_items_count' => fn ($q) => $q->where('is_completed', true)])
            ->orderBy('completed_at')
            ->get();

        return [
            'completed_count' => $completed->count(),
            'completed' => $completed->map(fn (Task $task) => [
                'label' => $task->completion_note ?: $task->title,
                'url' => route('tasks.show', $task),
                ...TaskReportDetails::forTask($task),
            ]),
            'pending_breakdown' => $this->classifier->counts(
                Task::query()->where('primary_assignee_id', $user->id)->whereNull('completed_at')->whereNull('archived_at')
            ),
        ];
    }
}
