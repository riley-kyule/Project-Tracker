<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskCollaboratorAdded;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskAssigneeController extends Controller
{
    private const ADDABLE_TYPES = ['collaborator', 'reviewer', 'watcher'];

    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'assignment_type' => ['required', Rule::in(self::ADDABLE_TYPES)],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        // Deliberately not gated on Gate::forUser($user)->allows('view', $task) — the
        // whole point of adding someone here is to grant a person who couldn't
        // otherwise see this task (e.g. outside the board's department) access to
        // it specifically; TaskPolicy::view() grants that once they're attached below.
        abort_unless($user->isActive(), 422, 'That user is not active.');

        $task->assignees()->syncWithoutDetaching([
            $user->id => ['assignment_type' => $validated['assignment_type']],
        ]);

        AuditLogger::log($task, 'assignee_added', [], ['user_id' => $user->id, 'assignment_type' => $validated['assignment_type']]);

        if ($user->id !== $request->user()->id && $user->wantsNotification('task_collaborator_added')) {
            $user->notify(new TaskCollaboratorAdded($task, $validated['assignment_type']));
        }

        return back();
    }

    public function destroy(Request $request, Task $task, User $assignee): RedirectResponse
    {
        Gate::authorize('update', $task);

        // Detach only rows this endpoint could have created — never the
        // primary "assignee" row, which stays in sync via TaskAssigneeSync
        // and is only ever changed by updating the task's primary assignee.
        $pivot = $task->assignees()->where('user_id', $assignee->id)->first()?->pivot;
        abort_unless($pivot && in_array($pivot->assignment_type, self::ADDABLE_TYPES, true), 404);

        $task->assignees()->detach($assignee->id);

        AuditLogger::log($task, 'assignee_removed', ['user_id' => $assignee->id], []);

        return back();
    }
}
