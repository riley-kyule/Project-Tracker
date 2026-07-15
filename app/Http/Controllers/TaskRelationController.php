<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskRelation;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskRelationController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'related_task_id' => ['required', 'integer', 'exists:tasks,id', Rule::notIn([$task->id])],
        ]);

        $related = Task::query()->findOrFail($validated['related_task_id']);
        Gate::authorize('view', $related);

        [$taskId, $relatedTaskId] = $task->id < $related->id ? [$task->id, $related->id] : [$related->id, $task->id];

        $relation = TaskRelation::query()->firstOrCreate(['task_id' => $taskId, 'related_task_id' => $relatedTaskId]);

        AuditLogger::log($task, 'relation_added', [], ['related_task_id' => $related->id, 'relation_id' => $relation->id]);

        return back();
    }

    public function destroy(Request $request, TaskRelation $relation): RedirectResponse
    {
        // Symmetric link, no "owning" side — either linked task's editor can remove it.
        $gate = Gate::forUser($request->user());
        abort_unless($gate->allows('update', $relation->task) || $gate->allows('update', $relation->relatedTask), 403);

        AuditLogger::log($relation->task, 'relation_removed', ['related_task_id' => $relation->related_task_id], []);
        $relation->delete();

        return back();
    }
}
