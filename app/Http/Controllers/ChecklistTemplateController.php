<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChecklistTemplateController extends Controller
{
    /** Saves an existing checklist's current item titles as a reusable named template. */
    public function store(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('create', ChecklistTemplate::class);
        Gate::authorize('update', $checklist->task);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        ChecklistTemplate::query()->create([
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'items' => $checklist->items()->orderBy('position')->pluck('title')->all(),
        ]);

        return back()->with('success', 'Saved as a checklist template.');
    }

    public function destroy(Request $request, ChecklistTemplate $checklistTemplate): RedirectResponse
    {
        Gate::authorize('delete', $checklistTemplate);

        $checklistTemplate->delete();

        return back();
    }
}
