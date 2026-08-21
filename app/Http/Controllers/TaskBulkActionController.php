<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskApprovalRequested;
use App\Notifications\TaskCollaboratorAdded;
use App\Services\AuditLogger;
use App\Services\TaskAssigneeSync;
use App\Services\TaskChecklistProgress;
use App\Services\TaskMover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bulk operations over a manager-selected set of tasks (e.g. from the task
 * report). A batch can span boards the acting user doesn't all manage — every
 * task is authorized before any of them are mutated, so a single
 * unauthorized task fails the whole batch rather than silently applying to
 * a partial set.
 */
class TaskBulkActionController extends Controller
{
    public function reassign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $tasks = Task::query()->with('board')->whereIn('id', $validated['task_ids'])->get();
        $assignee = $validated['assignee_id'] ? User::query()->findOrFail($validated['assignee_id']) : null;

        // Not gated on the assignee's board access — same as the single-task
        // path, becoming the assignee is itself what grants outside access.
        if ($assignee && ! $assignee->isActive()) {
            throw ValidationException::withMessages([
                'assignee_id' => 'The assignee must be active.',
            ]);
        }

        foreach ($tasks as $task) {
            Gate::authorize('update', $task);
        }

        DB::transaction(function () use ($tasks, $assignee) {
            foreach ($tasks as $task) {
                $previousAssigneeId = $task->primary_assignee_id;
                $task->update(['primary_assignee_id' => $assignee?->id]);
                TaskAssigneeSync::syncPrimary($task, $previousAssigneeId);
                AuditLogger::log($task, 'bulk_reassigned', ['primary_assignee_id' => $previousAssigneeId], ['primary_assignee_id' => $assignee?->id]);
            }
        });

        return back()->with('success', count($tasks).' task(s) reassigned.');
    }

    public function move(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
        ]);

        $tasks = Task::query()->whereIn('id', $validated['task_ids'])->get();
        $column = BoardColumn::query()->findOrFail($validated['board_column_id']);

        foreach ($tasks as $task) {
            Gate::authorize('move', $task);

            if ($task->board_id !== $column->board_id) {
                throw ValidationException::withMessages([
                    'board_column_id' => 'Every selected task must belong to the destination column\'s board.',
                ]);
            }
        }

        // One starting position, then a local counter — not a fresh MAX()
        // query per task. TaskMover::move() still re-clamps against a live
        // query inside its own transaction, so this is purely a query-count
        // fix, not a correctness dependency.
        //
        // Deliberately not wrapped in one outer transaction: TaskMover::move()
        // dispatches recurrence generation and notifications right after its
        // own per-task commit (persist-before-dispatch, see AGENTS.md). An
        // outer transaction around the whole batch would fire those before
        // the batch itself is durable, so a later task's failure could leave
        // a sent notification describing a change that then rolled back.
        // Each task's move stays its own atomic, independently valid unit.
        $position = (int) Task::query()->where('board_column_id', $column->id)->max('position') + 1;

        foreach ($tasks as $task) {
            TaskMover::move($task, $column, $position);
            $position++;
        }

        return back()->with('success', count($tasks).' task(s) moved.');
    }

    /** No notification dispatch (matches TaskController::destroy()), so the whole batch is one all-or-nothing transaction, unlike move()/addCollaborator()/requestApproval() below. */
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
        ]);

        $tasks = Task::query()->whereIn('id', $validated['task_ids'])->get();

        foreach ($tasks as $task) {
            Gate::authorize('delete', $task);
        }

        DB::transaction(function () use ($tasks) {
            foreach ($tasks as $task) {
                AuditLogger::log($task, 'deleted', ['title' => $task->title], []);
                $task->delete();
            }
        });

        return back()->with('success', count($tasks).' task(s) deleted.');
    }

    /** Mirrors TaskController::duplicate() per task — same fields, labels, checklists, and non-primary assignees carried over. No notification dispatch, so one outer transaction is safe. */
    public function duplicate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
        ]);

        $tasks = Task::query()->with(['board', 'column', 'labels', 'checklists.items', 'assignees'])->whereIn('id', $validated['task_ids'])->get();

        foreach ($tasks as $task) {
            Gate::authorize('view', $task);
            Gate::authorize('create', [Task::class, $task->board]);
        }

        $copies = DB::transaction(function () use ($request, $tasks) {
            return $tasks->map(function (Task $task) use ($request) {
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
                $copy->labels()->sync($task->labels->pluck('id'));

                foreach ($task->checklists as $checklist) {
                    $checklistCopy = $copy->checklists()->create(['name' => $checklist->name, 'position' => $checklist->position]);

                    foreach ($checklist->items as $item) {
                        $checklistCopy->items()->create(['title' => $item->title, 'position' => $item->position]);
                    }
                }

                AuditLogger::log($copy, 'created', [], ['title' => $copy->title, 'duplicated_from' => $task->id]);

                TaskAssigneeSync::syncPrimary($copy, null);

                $task->assignees
                    ->filter(fn (User $user) => in_array($user->pivot->assignment_type, ['collaborator', 'reviewer', 'watcher'], true))
                    ->each(fn (User $user) => $copy->assignees()->syncWithoutDetaching([
                        $user->id => ['assignment_type' => $user->pivot->assignment_type],
                    ]));

                return $copy;
            });
        });

        $copies->each(fn (Task $copy) => TaskChecklistProgress::sync($copy));

        return back()->with('success', count($copies).' task(s) duplicated.');
    }

    /** No notification dispatch (matches the auto_reset_frequency path in TaskController::update()), so one outer transaction is safe. */
    public function setAutoRenew(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'auto_reset_frequency' => ['nullable', Rule::in(Task::AUTO_RESET_FREQUENCIES)],
        ]);

        $tasks = Task::query()->whereIn('id', $validated['task_ids'])->get();

        foreach ($tasks as $task) {
            Gate::authorize('manageRecurrence', $task);
        }

        DB::transaction(function () use ($tasks, $validated) {
            foreach ($tasks as $task) {
                $old = $task->auto_reset_frequency;
                $task->update(['auto_reset_frequency' => $validated['auto_reset_frequency'] ?? null]);
                AuditLogger::log($task, 'auto_reset_frequency_changed', ['auto_reset_frequency' => $old], ['auto_reset_frequency' => $task->auto_reset_frequency]);
            }
        });

        return back()->with('success', count($tasks).' task(s) updated.');
    }

    /**
     * Mirrors TaskAssigneeController::store() per task, including its
     * notification — deliberately not one outer transaction (see move()'s
     * reasoning): each task's sync+audit+notify stays its own unit so a
     * later task's failure can't leave a sent notification describing a
     * change that then rolled back.
     */
    public function addCollaborator(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'assignment_type' => ['required', Rule::in(['collaborator', 'reviewer', 'watcher'])],
        ]);

        $tasks = Task::query()->whereIn('id', $validated['task_ids'])->get();
        $user = User::query()->findOrFail($validated['user_id']);
        abort_unless($user->isActive(), 422, 'That user is not active.');

        foreach ($tasks as $task) {
            Gate::authorize('update', $task);
        }

        foreach ($tasks as $task) {
            $task->assignees()->syncWithoutDetaching([$user->id => ['assignment_type' => $validated['assignment_type']]]);
            AuditLogger::log($task, 'assignee_added', [], ['user_id' => $user->id, 'assignment_type' => $validated['assignment_type']]);

            if ($user->id !== $request->user()->id && $user->wantsNotification('task_collaborator_added')) {
                $user->notify(new TaskCollaboratorAdded($task, $validated['assignment_type']));
            }
        }

        return back()->with('success', count($tasks).' task(s) updated.');
    }

    /** Mirrors TaskApprovalController::request() per task — same not-one-transaction reasoning as addCollaborator(). */
    public function requestApproval(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_ids' => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $tasks = Task::query()->whereIn('id', $validated['task_ids'])->get();
        $reviewer = User::query()->findOrFail($validated['reviewer_id']);

        foreach ($tasks as $task) {
            Gate::authorize('update', $task);
        }

        foreach ($tasks as $task) {
            $task->update([
                'approval_status' => Task::APPROVAL_PENDING,
                'approver_id' => $reviewer->id,
                'approved_at' => null,
                'approval_note' => null,
            ]);

            AuditLogger::log($task, 'approval_requested', [], ['approver_id' => $reviewer->id]);

            if ($reviewer->id !== $request->user()->id && $reviewer->wantsNotification('task_approval_requested')) {
                $reviewer->notify(new TaskApprovalRequested($task, $request->user()));
            }
        }

        return back()->with('success', count($tasks).' task(s) submitted for approval.');
    }
}
