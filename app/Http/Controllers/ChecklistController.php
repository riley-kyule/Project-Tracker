<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChecklistController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $task->checklists()->create([
            'name' => $validated['name'],
            'position' => (int) $task->checklists()->max('position') + 1,
        ]);

        return back();
    }

    public function destroy(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist->task);

        $checklist->delete();

        return back();
    }

    public function storeItem(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist->task);

        $validated = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $checklist->items()->create([
            'title' => $validated['title'],
            'position' => (int) $checklist->items()->max('position') + 1,
        ]);

        return back();
    }

    public function updateItem(Request $request, ChecklistItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->checklist->task);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_completed', $validated)) {
            $validated['completed_by'] = $validated['is_completed'] ? $request->user()->id : null;
            $validated['completed_at'] = $validated['is_completed'] ? now() : null;
        }

        $item->update($validated);

        return back();
    }

    public function destroyItem(Request $request, ChecklistItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->checklist->task);

        $item->delete();

        return back();
    }
}
