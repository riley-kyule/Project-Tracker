<?php

namespace App\Services\Reports;

use App\Models\Comment;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskStatusClassifier;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds a department's daily-summary content for a specific business day.
 * Day boundaries are computed against the company's configured timezone and
 * converted to UTC before querying — timestamp columns are stored in UTC, so
 * a task completed at 22:30 UTC is already "tomorrow" in Nairobi and must
 * land in the right business day either way.
 */
class DepartmentSummaryBuilder
{
    public function __construct(private readonly TaskStatusClassifier $classifier = new TaskStatusClassifier) {}

    /**
     * @return array{
     *     name: string,
     *     completed_today: int,
     *     pending: int,
     *     pending_breakdown: array<string, int>,
     *     breakdown: Collection,
     *     comments: Collection,
     *     progress_notes: Collection,
     *     reopened_today: Collection,
     *     completeness: array{active_members: int, members_with_activity: int, missing_activity: int, tasks_updated_today: int, overdue: int},
     * }
     */
    public function build(Department $department, Carbon $businessDay, string $timezone): array
    {
        [$start, $end] = $this->dayBounds($businessDay, $timezone);
        $pendingBreakdown = $this->pendingBreakdown($department->id);

        return [
            'name' => $department->name,
            'completed_today' => $this->completedCount($department->id, $start, $end),
            'pending' => array_sum($pendingBreakdown),
            'pending_breakdown' => $pendingBreakdown,
            'breakdown' => $this->completedBreakdown($department->id, $start, $end),
            'comments' => $this->commentsToday($department->id, $start, $end),
            'progress_notes' => $this->progressNotesToday($department->id, $start, $end),
            'reopened_today' => $this->reopenedToday($department->id, $start, $end),
            'completeness' => $this->completeness($department->id, $start, $end, $pendingBreakdown[TaskStatusClassifier::OVERDUE]),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} start (inclusive) / end (exclusive) of the business day, in UTC */
    public function dayBounds(Carbon $businessDay, string $timezone): array
    {
        $start = Carbon::parse($businessDay->toDateString(), $timezone)->startOfDay()->utc();

        return [$start, $start->copy()->addDay()];
    }

    private function completedCount(int $departmentId, Carbon $start, Carbon $end): int
    {
        return Task::query()
            ->where('department_id', $departmentId)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->count();
    }

    /** @return array<string, int> one of TaskStatusClassifier::ALL => count of open tasks in that bucket */
    private function pendingBreakdown(int $departmentId): array
    {
        return $this->classifier->counts(
            Task::query()->where('department_id', $departmentId)->whereNull('completed_at')->whereNull('archived_at')
        );
    }

    /** @return Collection<string, Collection<int, array{label: string, url: string}>> assignee name => tasks they completed today */
    private function completedBreakdown(int $departmentId, Carbon $start, Carbon $end): Collection
    {
        return Task::query()
            ->where('department_id', $departmentId)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->with('assignee:id,name')
            ->orderBy('completed_at')
            ->get()
            ->groupBy(fn (Task $task) => $task->assignee->name ?? 'Unassigned')
            ->map(fn (Collection $tasks) => $tasks->map(fn (Task $task) => [
                'label' => $this->completionLine($task),
                'url' => route('tasks.show', $task),
            ]));
    }

    /**
     * Prefers the completion note captured at complete-time over the bare
     * task title, and annotates a completion that isn't a clean first-time
     * finish — a task that was reopened before, or sent back for changes,
     * reads differently from one that just got done.
     */
    private function completionLine(Task $task): string
    {
        $line = $task->completion_note ?: $task->title;

        if ($task->reopened_at !== null) {
            $line .= ' (previously reopened)';
        }

        if ($task->approval_status === Task::APPROVAL_REJECTED) {
            $line .= ' (returned for changes)';
        } elseif ($task->approval_status === Task::APPROVAL_APPROVED) {
            $line .= ' (approved)';
        }

        return $line;
    }

    /** @return Collection<string, Collection<int, array{label: string, url: string}>> assignee name => tasks reopened today (currently still open) */
    private function reopenedToday(int $departmentId, Carbon $start, Carbon $end): Collection
    {
        return Task::query()
            ->where('department_id', $departmentId)
            ->whereNull('completed_at')
            ->where('reopened_at', '>=', $start)
            ->where('reopened_at', '<', $end)
            ->with('assignee:id,name')
            ->orderBy('reopened_at')
            ->get()
            ->groupBy(fn (Task $task) => $task->assignee->name ?? 'Unassigned')
            ->map(fn (Collection $tasks) => $tasks->map(fn (Task $task) => [
                'label' => $task->title,
                'url' => route('tasks.show', $task),
            ]));
    }

    /** @return Collection<int, array{title: string, url: string, lines: Collection<int, string>}> one entry per commented-on task, ordinary comments only */
    private function commentsToday(int $departmentId, Carbon $start, Carbon $end): Collection
    {
        return $this->groupCommentsByTask(
            Comment::query()->ordinary(),
            $departmentId,
            $start,
            $end,
            fn (Comment $comment) => ($comment->user->name ?? 'Unknown').': '.Str::limit($comment->body, 140),
        );
    }

    /** @return Collection<int, array{title: string, url: string, lines: Collection<int, string>}> one entry per task with a progress note today */
    private function progressNotesToday(int $departmentId, Carbon $start, Carbon $end): Collection
    {
        return $this->groupCommentsByTask(
            Comment::query()->progressNotes(),
            $departmentId,
            $start,
            $end,
            fn (Comment $comment) => ($comment->user->name ?? 'Unknown')." [{$comment->note_type}]: ".Str::limit($comment->body, 140),
        );
    }

    /** @return Collection<int, array{title: string, url: string, lines: Collection<int, string>}> */
    private function groupCommentsByTask(Builder $query, int $departmentId, Carbon $start, Carbon $end, Closure $formatLine): Collection
    {
        return $query
            ->where('commentable_type', Task::class)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereHas('commentable', fn ($q) => $q->where('department_id', $departmentId))
            ->with(['user:id,name', 'commentable:id,title'])
            ->orderBy('created_at')
            ->get()
            ->groupBy('commentable_id')
            ->map(fn (Collection $comments) => [
                'title' => $comments->first()->commentable->title ?? 'Unknown task',
                'url' => route('tasks.show', $comments->first()->commentable_id),
                'lines' => $comments->map($formatLine),
            ])
            ->values();
    }

    /**
     * Data-completeness signals for the department, never a per-person
     * ranking: how many active members there are, how many of them show up
     * anywhere in today's activity (a completed task or a comment), and how
     * many don't.
     */
    private function completeness(int $departmentId, Carbon $start, Carbon $end, int $overdueCount): array
    {
        $activeMemberIds = User::query()
            ->where('department_id', $departmentId)
            ->where('status', User::STATUS_ACTIVE)
            ->pluck('id');

        $completedUserIds = Task::query()
            ->where('department_id', $departmentId)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->whereNotNull('primary_assignee_id')
            ->pluck('primary_assignee_id');

        $commentUserIds = Comment::query()
            ->where('commentable_type', Task::class)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereHas('commentable', fn ($query) => $query->where('department_id', $departmentId))
            ->pluck('user_id');

        $activeWithActivity = $activeMemberIds->intersect($completedUserIds->merge($commentUserIds))->unique();

        $tasksUpdatedToday = Task::query()
            ->where('department_id', $departmentId)
            ->where('updated_at', '>=', $start)
            ->where('updated_at', '<', $end)
            ->count();

        return [
            'active_members' => $activeMemberIds->count(),
            'members_with_activity' => $activeWithActivity->count(),
            'missing_activity' => $activeMemberIds->diff($activeWithActivity)->count(),
            'tasks_updated_today' => $tasksUpdatedToday,
            'overdue' => $overdueCount,
        ];
    }
}
