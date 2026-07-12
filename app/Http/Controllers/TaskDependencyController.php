<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskDependency;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskDependencyController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'predecessor_task_id' => ['required', 'integer', 'exists:tasks,id', Rule::notIn([$task->id])],
        ]);

        $predecessor = Task::query()->findOrFail($validated['predecessor_task_id']);
        Gate::authorize('view', $predecessor);

        abort_if(
            $this->wouldCreateCycle($validated['predecessor_task_id'], $task->id),
            422,
            'This dependency would create a cycle.',
        );

        $dependency = TaskDependency::query()->firstOrCreate([
            'predecessor_task_id' => $predecessor->id,
            'successor_task_id' => $task->id,
        ]);

        AuditLogger::log($task, 'dependency_added', [], ['predecessor_task_id' => $predecessor->id, 'dependency_id' => $dependency->id]);

        return back();
    }

    public function destroy(Request $request, TaskDependency $dependency): RedirectResponse
    {
        $task = $dependency->successor;
        Gate::authorize('update', $task);

        $dependency->delete();

        AuditLogger::log($task, 'dependency_removed', ['predecessor_task_id' => $dependency->predecessor_task_id], []);

        return back();
    }

    /**
     * BFS forward from the would-be successor through existing "precedes" edges;
     * if the would-be predecessor is reachable, the new edge would close a loop.
     */
    private function wouldCreateCycle(int $newPredecessorId, int $newSuccessorId): bool
    {
        $visited = [];
        $queue = [$newSuccessorId];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (in_array($current, $visited, true)) {
                continue;
            }
            $visited[] = $current;

            $next = TaskDependency::query()->where('predecessor_task_id', $current)->pluck('successor_task_id');

            foreach ($next as $nextId) {
                if ($nextId === $newPredecessorId) {
                    return true;
                }
                $queue[] = $nextId;
            }
        }

        return false;
    }
}
