<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TaskAssigneeSync;
use App\Services\TaskMover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        foreach ($tasks as $task) {
            Gate::authorize('update', $task);

            if ($assignee && (! $assignee->isActive() || Gate::forUser($assignee)->denies('view', $task->board))) {
                throw ValidationException::withMessages([
                    'assignee_id' => "The assignee must be active and able to access every selected task's board.",
                ]);
            }
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

        foreach ($tasks as $task) {
            $position = (int) Task::query()->where('board_column_id', $column->id)->max('position') + 1;
            TaskMover::move($task, $column, $position);
        }

        return back()->with('success', count($tasks).' task(s) moved.');
    }
}
