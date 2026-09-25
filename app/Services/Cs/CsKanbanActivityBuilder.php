<?php

namespace App\Services\Cs;

use App\Models\AuditLog;
use App\Models\Task;
use App\Models\User;
use App\Services\Reports\DepartmentSummaryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * One person's Kanban activity on the Customer Service department's boards
 * for a business day, for the combined daily report: tasks they created,
 * moved, completed, plus what is blocked and overdue on their plate at close.
 * The day window is the company-timezone calendar day converted to UTC by the
 * same DepartmentSummaryBuilder::dayBounds() the department summary uses, so
 * the two reports agree about what "today" is.
 */
class CsKanbanActivityBuilder
{
    private const LIST_LIMIT = 10;

    public function __construct(private readonly DepartmentSummaryBuilder $days) {}

    /**
     * @return array{
     *     counts: array{created: int, moved: int, completed: int, blocked: int, overdue: int},
     *     created: list<array{title: string, url: string}>,
     *     moved: list<array{title: string, url: string}>,
     *     completed: list<array{title: string, url: string}>,
     *     blocked: list<array{title: string, url: string}>,
     *     overdue: list<array{title: string, url: string}>,
     * }
     */
    public function forUser(User $user, Carbon $businessDay, string $timezone): array
    {
        [$start, $end] = $this->days->dayBounds($businessDay, $timezone);

        $created = $this->onCsBoards(Task::query())
            ->where('created_by', $user->id)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->orderBy('created_at')->get();

        $completed = $this->onCsBoards(Task::query())
            ->where(fn (Builder $q) => $q->where('primary_assignee_id', $user->id)->orWhereHas('assignees', fn (Builder $a) => $a->whereKey($user->id)))
            ->where('completed_at', '>=', $start)->where('completed_at', '<', $end)
            ->orderBy('completed_at')->get();

        $movedIds = AuditLog::query()
            ->where('auditable_type', (new Task)->getMorphClass())
            ->where('event', 'moved')
            ->where('actor_id', $user->id)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->pluck('auditable_id')->unique();
        $moved = $this->onCsBoards(Task::query())->whereIn('id', $movedIds)->orderBy('title')->get();

        $open = $this->onCsBoards(Task::query())
            ->whereNull('completed_at')->whereNull('archived_at')
            ->where(fn (Builder $q) => $q->where('primary_assignee_id', $user->id)->orWhereHas('assignees', fn (Builder $a) => $a->whereKey($user->id)))
            ->with('column')
            ->get();

        $blocked = $open->filter(fn (Task $t) => $t->column?->semantic_status === 'blocked')->values();
        $overdue = $open->filter(fn (Task $t) => $t->due_at !== null && $t->due_at->lt($end))->values();

        return [
            'counts' => [
                'created' => $created->count(),
                'moved' => $moved->count(),
                'completed' => $completed->count(),
                'blocked' => $blocked->count(),
                'overdue' => $overdue->count(),
            ],
            'created' => $this->lines($created),
            'moved' => $this->lines($moved),
            'completed' => $this->lines($completed),
            'blocked' => $this->lines($blocked),
            'overdue' => $this->lines($overdue),
        ];
    }

    /** Tasks on boards belonging to the Customer Service department or its sub-departments. */
    private function onCsBoards(Builder $query): Builder
    {
        $ids = CsAccess::department()?->descendantIds() ?? [];

        return $query->whereHas('board', fn (Builder $b) => $b->whereIn('department_id', $ids));
    }

    /** @param  iterable<Task>  $tasks */
    private function lines(iterable $tasks): array
    {
        $lines = [];

        foreach ($tasks as $task) {
            if (count($lines) >= self::LIST_LIMIT) {
                break;
            }

            $lines[] = ['title' => $task->title, 'url' => route('tasks.show', $task)];
        }

        return $lines;
    }
}
