<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\AuditLogger;
use App\Services\TaskAssigneeSync;
use App\Services\TaskMover;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function store(StoreTaskRequest $request, Board $board): RedirectResponse
    {
        Gate::authorize('create', [Task::class, $board]);

        $column = BoardColumn::query()
            ->where('board_id', $board->id)
            ->findOrFail($request->validated('board_column_id'));

        $this->guardAssigneeCanAccessBoard($request->validated('primary_assignee_id'), $board);

        $task = DB::transaction(function () use ($request, $board, $column) {
            $task = Task::create([
                ...$request->validated(),
                'board_id' => $board->id,
                'department_id' => $board->department_id,
                'created_by' => $request->user()->id,
                'position' => (int) $column->tasks()->max('position') + 1,
            ]);

            $task->forceFill(['task_number' => $task->id])->save();

            AuditLogger::log($task, 'created', [], ['title' => $task->title]);

            return $task;
        });

        TaskAssigneeSync::syncPrimary($task, null);
        $this->notifyAssignee($task, null, $request->user());

        return back();
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        if ($request->has('primary_assignee_id')) {
            $this->guardAssigneeCanAccessBoard($request->validated('primary_assignee_id'), $task->board);
        }

        $validated = $request->safe()->except(['label_ids', 'ceo_priority', 'confidentiality']);

        if ($request->has('ceo_priority') && $request->user()->hasAnyRole(['CEO', 'Administrator'])) {
            $validated['ceo_priority'] = $request->boolean('ceo_priority');
        }

        if ($request->has('confidentiality') && Gate::forUser($request->user())->allows('manageConfidentiality', $task)) {
            $validated['confidentiality'] = $request->validated('confidentiality');
        }

        $previousAssignee = $task->primary_assignee_id;
        // Reaching 100% must count as completed everywhere (dashboards key off
        // completed_at, not progress_percentage) — route it through TaskMover so
        // completed_at, the completion column, and recurrence/notifications all
        // stay in the same place a drag-to-Completed move would put them.
        $completingByProgress = ($validated['progress_percentage'] ?? null) === 100 && $task->completed_at === null;

        DB::transaction(function () use ($request, $task, $validated) {
            $old = $task->only(array_keys($validated));
            $task->update($validated);

            if ($request->has('label_ids')) {
                $task->labels()->sync($request->validated('label_ids'));
            }

            $changes = array_diff_assoc(
                array_map(fn ($v) => is_scalar($v) ? $v : json_encode($v), $validated),
                array_map(fn ($v) => is_scalar($v) ? $v : json_encode($v), $old),
            );

            if ($changes !== [] || $request->has('label_ids')) {
                AuditLogger::log($task, 'updated', array_intersect_key($old, $changes), $changes);
            }
        });

        $task->refresh();

        if ($completingByProgress) {
            $completionColumn = $task->board->columns()->where('is_completion_column', true)->first();

            if ($completionColumn && $completionColumn->id !== $task->board_column_id) {
                $position = (int) $completionColumn->tasks()->max('position') + 1;
                $task = TaskMover::move($task, $completionColumn, $position);
            }
        }

        TaskAssigneeSync::syncPrimary($task, $previousAssignee);
        $this->notifyAssignee($task, $previousAssignee, $request->user());

        return back();
    }

    public function move(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('move', $task);

        $validated = $request->validate([
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'position' => ['required', 'integer', 'min:1'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $column = BoardColumn::query()
            ->where('board_id', $task->board_id)
            ->findOrFail($validated['board_column_id']);

        // Dependency check only applies when a task is being started (WORKFLOWS.md).
        if ($column->semantic_status === 'active') {
            $this->guardDependencies($request, $task, $validated['override_reason'] ?? null);
        }

        if ($column->is_completion_column && $task->approval_status === Task::APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'approval' => 'This task is awaiting reviewer approval before it can be completed.',
            ]);
        }

        TaskMover::move($task, $column, $validated['position']);

        return back();
    }

    private function guardDependencies(Request $request, Task $task, ?string $overrideReason): void
    {
        $blocking = $task->unresolvedDependencies()->with('predecessor:id,title')->get();

        if ($blocking->isEmpty()) {
            return;
        }

        if ($overrideReason === null) {
            $titles = $blocking->pluck('predecessor.title')->implode(', ');

            throw ValidationException::withMessages([
                'dependencies' => "Blocked by incomplete prerequisite(s): {$titles}.",
            ]);
        }

        try {
            Gate::authorize('overrideDependency', $task);
        } catch (AuthorizationException) {
            throw ValidationException::withMessages([
                'dependencies' => 'You are not authorized to override this dependency.',
            ]);
        }

        DB::transaction(function () use ($blocking, $request, $overrideReason, $task) {
            foreach ($blocking as $dependency) {
                $dependency->update([
                    'overridden_at' => now(),
                    'overridden_by' => $request->user()->id,
                    'override_reason' => $overrideReason,
                ]);
            }

            AuditLogger::log($task, 'dependency_overridden', [], ['reason' => $overrideReason]);
        });
    }

    public function activity(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return response()->json(
            $task->auditLogs()->with('actor:id,name')->limit(50)->get(),
        );
    }

    public function detail(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return response()->json([
            'comments' => $task->comments()
                ->whereNull('parent_id')
                ->with(['user:id,name', 'replies.user:id,name'])
                ->oldest()
                ->get(),
            'checklists' => $task->checklists()->with('items')->get(),
            'canEditChecklist' => $request->user()->can('update', $task),
            'attachments' => $task->attachments()->with('uploader:id,name')->latest()->get(),
            'dependencies' => $task->dependencies()->with('predecessor:id,title,task_number,completed_at')->get(),
            'blocking' => $task->blocks()->with('successor:id,title,task_number')->get(),
            'relations' => $task->relationsAsTask()->with('relatedTask:id,title,task_number')->get()
                ->map(fn ($r) => ['id' => $r->id, 'task' => $r->relatedTask])
                ->concat(
                    $task->relationsAsRelatedTask()->with('task:id,title,task_number')->get()
                        ->map(fn ($r) => ['id' => $r->id, 'task' => $r->task]),
                )
                ->values(),
            'recurrenceRule' => $task->recurrenceRule()->first(['id', 'frequency', 'interval_value', 'next_run_at', 'is_active', 'template_task_id']),
            'canManageRecurrence' => $request->user()->can('manageRecurrence', $task),
            'timeEntries' => $task->timeEntries()->with(['user:id,name', 'approvedBy:id,name'])->latest('started_at')->get(),
            'canApproveTime' => $request->user()->can('approveTimeEntry', $task),
            'estimatedMinutes' => $task->estimated_minutes,
            'actualMinutes' => $task->actual_minutes,
            'approval' => [
                'status' => $task->approval_status,
                'approver' => $task->approver()->first(['users.id', 'users.name']),
                'note' => $task->approval_note,
            ],
            'canReviewApproval' => $request->user()->can('reviewApproval', $task),
            'assignees' => $task->assignees()->get(['users.id', 'users.name'])->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'assignment_type' => $u->pivot->assignment_type,
            ]),
            'confidentiality' => $task->confidentiality,
            'confidentialGrants' => $task->confidentialGrants()->get(['users.id', 'users.name']),
            'canManageConfidentiality' => $request->user()->can('manageConfidentiality', $task),
            'activity' => $task->auditLogs()->with('actor:id,name')->limit(50)->get(),
        ]);
    }

    private function notifyAssignee(Task $task, ?int $previousAssigneeId, User $actor): void
    {
        if ($task->primary_assignee_id === null
            || $task->primary_assignee_id === $previousAssigneeId
            || $task->primary_assignee_id === $actor->id) {
            return;
        }

        if ($task->assignee?->wantsNotification('task_assigned')) {
            $task->assignee->notify(new TaskAssigned($task, $actor));
        }
    }

    private function guardAssigneeCanAccessBoard(?int $assigneeId, Board $board): void
    {
        if ($assigneeId === null) {
            return;
        }

        $assignee = User::query()->findOrFail($assigneeId);

        if (! $assignee->isActive() || Gate::forUser($assignee)->denies('view', $board)) {
            throw ValidationException::withMessages([
                'primary_assignee_id' => 'The assignee must be active and able to access this board.',
            ]);
        }
    }
}
