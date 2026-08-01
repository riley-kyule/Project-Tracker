<?php

namespace App\Services\Reports;

use App\Models\Department;
use App\Models\Task;
use App\Services\TaskStatusClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** The CEO's weekly rollup across every department — same week-ending convention and completion-note preference as the personal weekly summary, just company-wide. */
class CeoWeeklySummaryBuilder
{
    public function __construct(private readonly TaskStatusClassifier $classifier = new TaskStatusClassifier) {}

    /** @return array{rows: Collection, totalCompleted: int} */
    public function build(Carbon $weekEndDay, string $timezone): array
    {
        [$start, $end] = WeekBounds::forWeekEndingOn($weekEndDay, $timezone);

        $rows = Department::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => $this->departmentRow($department, $start, $end));

        return [
            'rows' => $rows,
            'totalCompleted' => (int) $rows->sum('completed_count'),
        ];
    }

    /** @return array{name: string, completed_count: int, completed: Collection<int, array{label: string, url: string}>, pending_breakdown: array<string, int>} */
    private function departmentRow(Department $department, Carbon $start, Carbon $end): array
    {
        $completed = Task::query()
            ->where('department_id', $department->id)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->orderBy('completed_at')
            ->get();

        return [
            'name' => $department->name,
            'completed_count' => $completed->count(),
            'completed' => $completed->map(fn (Task $task) => [
                'label' => $task->completion_note ?: $task->title,
                'url' => route('tasks.show', $task),
            ]),
            'pending_breakdown' => $this->classifier->counts(
                Task::query()->where('department_id', $department->id)->whereNull('completed_at')->whereNull('archived_at')
            ),
        ];
    }
}
