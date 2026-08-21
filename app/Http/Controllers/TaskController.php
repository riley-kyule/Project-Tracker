<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Mail\TaskAssignedMail;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\ChecklistTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\AuditLogger;
use App\Services\PushNotifier;
use App\Services\TaskAssigneeSync;
use App\Services\TaskChecklistProgress;
use App\Services\TaskMover;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    /** Stable permalink: resolves the task's board and hands off to it with ?task= set, so the board page can open its card. */
    public function showOnBoard(Task $task): RedirectResponse
    {
        Gate::authorize('view', $task);

        return redirect()->route('boards.show', ['board' => $task->board_id, 'task' => $task->id]);
    }

    public function store(StoreTaskRequest $request, Board $board): RedirectResponse
    {
        Gate::authorize('create', [Task::class, $board]);

        $column = BoardColumn::query()
            ->where('board_id', $board->id)
            ->findOrFail($request->validated('board_column_id'));

        $this->guardAssigneeIsActive($request->validated('primary_assignee_id'));

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

    /**
     * Same concept as ChecklistController::duplicate(): copies the
     * definitional/structural fields and checklists (items reset to
     * incomplete), but none of the source task's history or in-progress
     * state — no comments, attachments, time entries, completion/approval
     * status, dependencies, or its own recurrence settings.
     */
    public function duplicate(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('view', $task);
        Gate::authorize('create', [Task::class, $task->board]);

        $copy = DB::transaction(function () use ($request, $task) {
            $copy = Task::create([
                'title' => $task->title.' (copy)',
                'description' => $task->description,
                'department_id' => $task->department_id,
                'board_id' => $task->board_id,
                'board_column_id' => $task->board_column_id,
                'project_id' => $task->project_id,
                'priority' => $task->priority,
                'primary_assignee_id' => $task->primary_assignee_id,
                'work_location' => $task->work_location,
                'estimated_minutes' => $task->estimated_minutes,
                'confidentiality' => $task->confidentiality,
                'created_by' => $request->user()->id,
                'position' => (int) $task->column->tasks()->max('position') + 1,
            ]);

            $copy->forceFill(['task_number' => $copy->id])->save();

            $copy->labels()->sync($task->labels()->pluck('labels.id'));

            foreach ($task->checklists as $checklist) {
                $checklistCopy = $copy->checklists()->create([
                    'name' => $checklist->name,
                    'position' => $checklist->position,
                ]);

                foreach ($checklist->items as $item) {
                    $checklistCopy->items()->create([
                        'title' => $item->title,
                        'position' => $item->position,
                    ]);
                }
            }

            AuditLogger::log($copy, 'created', [], ['title' => $copy->title, 'duplicated_from' => $task->id]);

            return $copy;
        });

        TaskChecklistProgress::sync($copy);
        TaskAssigneeSync::syncPrimary($copy, null);

        // Non-primary assignments (collaborator/reviewer/watcher) carry over
        // too — the primary "assignee" row is already handled above.
        $task->assignees()
            ->wherePivotIn('assignment_type', ['collaborator', 'reviewer', 'watcher'])
            ->get()
            ->each(fn (User $user) => $copy->assignees()->syncWithoutDetaching([
                $user->id => ['assignment_type' => $user->pivot->assignment_type],
            ]));

        return back();
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        if ($request->has('primary_assignee_id')) {
            $this->guardAssigneeIsActive($request->validated('primary_assignee_id'));
        }

        $validated = $request->safe()->except(['label_ids', 'ceo_priority', 'confidentiality', 'auto_reset_frequency']);

        if ($request->has('ceo_priority') && $request->user()->hasAnyRole(['CEO', 'Administrator'])) {
            $validated['ceo_priority'] = $request->boolean('ceo_priority');
        }

        if ($request->has('confidentiality') && Gate::forUser($request->user())->allows('manageConfidentiality', $task)) {
            $validated['confidentiality'] = $request->validated('confidentiality');
        }

        if ($request->has('auto_reset_frequency') && Gate::forUser($request->user())->allows('manageRecurrence', $task)) {
            $validated['auto_reset_frequency'] = $request->validated('auto_reset_frequency');
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
            $task = TaskMover::moveToCompletionColumnIfReady($task);
        }

        TaskAssigneeSync::syncPrimary($task, $previousAssignee);
        $this->notifyAssignee($task, $previousAssignee, $request->user());

        return back();
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        AuditLogger::log($task, 'deleted', ['title' => $task->title], []);
        $task->delete();

        return back();
    }

    public function move(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('move', $task);

        $validated = $request->validate([
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'position' => ['required', 'integer', 'min:1'],
            'override_reason' => ['nullable', 'string', 'max:500'],
            'completion_note' => ['nullable', 'string', 'max:2000'],
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

        TaskMover::move($task, $column, $validated['position'], $validated['completion_note'] ?? null);

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
            'canDelete' => $request->user()->can('delete', $task),
            'comments' => $task->comments()
                ->whereNull('parent_id')
                ->with(['user:id,name', 'replies.user:id,name'])
                ->oldest()
                ->get(),
            'checklists' => $task->checklists()->with('items')->get(),
            'canEditChecklist' => $request->user()->can('update', $task),
            'checklistTemplates' => ChecklistTemplate::query()
                ->orderBy('name')
                ->get(['id', 'name', 'created_by', 'items'])
                ->map(fn (ChecklistTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'item_count' => count($template->items),
                    'canDelete' => $request->user()->can('delete', $template),
                ]),
            'links' => $task->links()->with('creator:id,name')->get(),
            'canEditLinks' => $request->user()->can('update', $task),
            'project' => $task->project()->first(['projects.id', 'projects.name']),
            'projectOptions' => Project::query()->orderBy('name')->get(['id', 'name']),
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
            'autoResetFrequency' => $task->auto_reset_frequency,
            'lastAutoResetAt' => $task->last_auto_reset_at,
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
            Mail::to($task->assignee)->queue(new TaskAssignedMail($task, $actor));
            app(PushNotifier::class)->notify($task->assignee, 'task_assigned', [
                'title' => "New task: {$task->title}",
                'url' => url("/boards/{$task->board_id}?task={$task->id}"),
            ]);
        }
    }

    /**
     * Deliberately not gated on board access, same as TaskAssigneeController
     * — becoming the primary assignee is itself how someone outside the
     * board's department gets access to this task (TaskPolicy::view()).
     */
    private function guardAssigneeIsActive(?int $assigneeId): void
    {
        if ($assigneeId === null) {
            return;
        }

        $assignee = User::query()->findOrFail($assigneeId);

        if (! $assignee->isActive()) {
            throw ValidationException::withMessages([
                'primary_assignee_id' => 'The assignee must be active.',
            ]);
        }
    }
}
