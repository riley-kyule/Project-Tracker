<?php

namespace App\Http\Controllers;

use App\Models\SavedFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedFilterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'string', Rule::in([SavedFilter::SCOPE_REPORTS_TASKS])],
            'name' => ['required', 'string', 'max:100'],
            'filters' => ['required', 'array'],
        ]);

        SavedFilter::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'scope' => $validated['scope'], 'name' => $validated['name']],
            ['filters' => $validated['filters']],
        );

        return back()->with('success', 'Filter saved.');
    }

    public function destroy(Request $request, SavedFilter $savedFilter): RedirectResponse
    {
        abort_unless($savedFilter->user_id === $request->user()->id, 403);

        $savedFilter->delete();

        return back();
    }
}
