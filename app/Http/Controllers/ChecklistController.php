<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\Task;
use App\Services\AuditLogger;
use App\Services\TaskChecklistProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChecklistController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $checklist = $task->checklists()->create([
            'name' => $validated['name'],
            'position' => (int) $task->checklists()->max('position') + 1,
        ]);
        AuditLogger::log($task, 'checklist_created', [], ['checklist_id' => $checklist->id, 'name' => $checklist->name]);

        return back();
    }

    public function update(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist->task);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $old = $checklist->only(['name']);
        $checklist->update($validated);
        AuditLogger::log($checklist->task, 'checklist_renamed', $old, $checklist->only(['name']));

        return back();
    }

    public function reorder(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'checklist_ids' => ['required', 'array'],
            'checklist_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $currentIds = $task->checklists()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $requestedIds = array_map('intval', $validated['checklist_ids']);

        $sortedCurrentIds = $currentIds;
        $sortedRequestedIds = $requestedIds;
        sort($sortedCurrentIds);
        sort($sortedRequestedIds);

        abort_unless($sortedCurrentIds === $sortedRequestedIds, 422, 'Every checklist for this task must be included exactly once.');

        DB::transaction(function () use ($task, $currentIds, $requestedIds): void {
            foreach ($requestedIds as $index => $checklistId) {
                Checklist::query()->whereKey($checklistId)->update(['position' => $index + 1]);
            }

            AuditLogger::log($task, 'checklists_reordered', ['checklist_ids' => $currentIds], ['checklist_ids' => $requestedIds]);
        });

        return back();
    }

    public function destroy(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist->task);

        $task = $checklist->task;
        $old = $checklist->only(['id', 'name']);
        $checklist->delete();
        AuditLogger::log($task, 'checklist_removed', $old, []);
        TaskChecklistProgress::sync($task);

        return back();
    }

    public function duplicate(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist->task);

        $task = $checklist->task;

        $copy = DB::transaction(function () use ($checklist, $task) {
            $copy = $task->checklists()->create([
                'name' => $checklist->name.' (copy)',
                'position' => (int) $task->checklists()->max('position') + 1,
            ]);

            foreach ($checklist->items as $item) {
                $copy->items()->create([
                    'title' => $item->title,
                    'position' => $item->position,
                ]);
            }

            return $copy;
        });

        AuditLogger::log($task, 'checklist_created', [], ['checklist_id' => $copy->id, 'name' => $copy->name]);
        TaskChecklistProgress::sync($task);

        return back();
    }

    public function applyTemplate(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate(['template_id' => ['required', 'integer', 'exists:checklist_templates,id']]);
        $template = ChecklistTemplate::query()->findOrFail($validated['template_id']);

        $checklist = DB::transaction(function () use ($task, $template) {
            $checklist = $task->checklists()->create([
                'name' => $template->name,
                'position' => (int) $task->checklists()->max('position') + 1,
            ]);

            foreach ($template->items as $index => $title) {
                $checklist->items()->create(['title' => $title, 'position' => $index + 1]);
            }

            return $checklist;
        });

        AuditLogger::log($task, 'checklist_created', [], ['checklist_id' => $checklist->id, 'name' => $checklist->name, 'from_template' => $template->id]);
        TaskChecklistProgress::sync($task);

        return back();
    }

    public function storeItem(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist->task);

        $validated = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $item = $checklist->items()->create([
            'title' => $validated['title'],
            'position' => (int) $checklist->items()->max('position') + 1,
        ]);
        AuditLogger::log($checklist->task, 'checklist_item_created', [], ['item_id' => $item->id, 'title' => $item->title]);
        TaskChecklistProgress::sync($checklist->task);

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

        $old = $item->only(array_keys($validated));
        $item->update($validated);
        AuditLogger::log($item->checklist->task, 'checklist_item_updated', $old, $item->only(array_keys($validated)));

        if (array_key_exists('is_completed', $validated)) {
            TaskChecklistProgress::sync($item->checklist->task);
        }

        return back();
    }

    public function destroyItem(Request $request, ChecklistItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->checklist->task);

        $task = $item->checklist->task;
        $old = $item->only(['id', 'title']);
        $item->delete();
        AuditLogger::log($task, 'checklist_item_removed', $old, []);
        TaskChecklistProgress::sync($task);

        return back();
    }
}
