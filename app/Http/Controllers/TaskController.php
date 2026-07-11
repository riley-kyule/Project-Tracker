<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Services\AuditLogger;
use App\Services\TaskMover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function store(StoreTaskRequest $request, Board $board): RedirectResponse
    {
        Gate::authorize('create', [Task::class, $board]);

        $column = BoardColumn::query()
            ->where('board_id', $board->id)
            ->findOrFail($request->validated('board_column_id'));

        DB::transaction(function () use ($request, $board, $column) {
            $task = Task::create([
                ...$request->validated(),
                'board_id' => $board->id,
                'department_id' => $board->department_id,
                'created_by' => $request->user()->id,
                'position' => (int) $column->tasks()->max('position') + 1,
            ]);

            $task->forceFill(['task_number' => $task->id])->save();

            AuditLogger::log($task, 'created', [], ['title' => $task->title]);
        });

        return back();
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->safe()->except(['label_ids', 'ceo_priority']);

        if ($request->has('ceo_priority') && $request->user()->hasAnyRole(['CEO', 'Administrator'])) {
            $validated['ceo_priority'] = $request->boolean('ceo_priority');
        }

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

        return back();
    }

    public function move(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('move', $task);

        $validated = $request->validate([
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'position' => ['required', 'integer', 'min:1'],
        ]);

        $column = BoardColumn::query()
            ->where('board_id', $task->board_id)
            ->findOrFail($validated['board_column_id']);

        TaskMover::move($task, $column, $validated['position']);

        return back();
    }

    public function activity(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return response()->json(
            $task->auditLogs()->with('actor:id,name')->limit(50)->get(),
        );
    }
}
