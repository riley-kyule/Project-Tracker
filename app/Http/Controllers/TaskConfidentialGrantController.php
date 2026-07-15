<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskConfidentialGrantController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('manageConfidentiality', $task);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $task->confidentialGrants()->syncWithoutDetaching([
            $validated['user_id'] => ['granted_by' => $request->user()->id],
        ]);

        AuditLogger::log($task, 'confidential_grant_added', [], ['user_id' => $validated['user_id']]);

        return back();
    }

    public function destroy(Request $request, Task $task, User $user): RedirectResponse
    {
        Gate::authorize('manageConfidentiality', $task);

        $task->confidentialGrants()->detach($user->id);

        AuditLogger::log($task, 'confidential_grant_removed', ['user_id' => $user->id], []);

        return back();
    }
}
