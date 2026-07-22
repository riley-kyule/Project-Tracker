<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskLink;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskLinkController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $link = $task->links()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);
        AuditLogger::log($task, 'link_added', [], ['link_id' => $link->id, 'url' => $link->url]);

        return back();
    }

    public function destroy(Request $request, TaskLink $link): RedirectResponse
    {
        Gate::authorize('update', $link->task);

        $old = $link->only(['id', 'url', 'label']);
        $link->delete();
        AuditLogger::log($link->task, 'link_removed', $old, []);

        return back();
    }
}
