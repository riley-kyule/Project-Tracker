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

    /** @return array{completed_count: int, completed: Collection<int, string>, pending_breakdown: array<string, int>} */
    public function build(User $user, Carbon $weekEndDay, string $timezone): array
    {
        [$start, $end] = $this->weekBounds($weekEndDay, $timezone);

        $completed = Task::query()
            ->where('primary_assignee_id', $user->id)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->orderBy('completed_at')
            ->get();

        return [
            'completed_count' => $completed->count(),
            'completed' => $completed->map(fn (Task $task) => $task->completion_note ?: $task->title),
            'pending_breakdown' => $this->classifier->counts(
                Task::query()->where('primary_assignee_id', $user->id)->whereNull('completed_at')->whereNull('archived_at')
            ),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon} Monday 00:00 (inclusive) through the
     *                                     Saturday immediately after $weekEndDay (exclusive), in UTC
     */
    private function weekBounds(Carbon $weekEndDay, string $timezone): array
    {
        $localWeekEndDay = Carbon::parse($weekEndDay->toDateString(), $timezone)->startOfDay();
        $end = $localWeekEndDay->copy()->addDay()->utc();
        $start = $localWeekEndDay->copy()->subDays(4)->utc();

        return [$start, $end];
    }
}
