<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebsiteAssignmentController extends Controller
{
    /** Soft cap matching the "2-5 members at a time" team-size guidance. */
    private const MAX_PER_TEAM = 5;

    public function store(Request $request, Website $website): RedirectResponse
    {
        Gate::authorize('update', $website);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'team' => ['required', Rule::in(WebsiteAssignment::TEAMS)],
        ]);

        $existing = $website->assignments()->where('team', $validated['team'])->count();
        if ($existing >= self::MAX_PER_TEAM) {
            throw ValidationException::withMessages([
                'team' => 'This team already has the maximum of '.self::MAX_PER_TEAM.' members for this website.',
            ]);
        }

        $website->assignments()->firstOrCreate($validated);

        return back()->with('success', 'Member assigned.');
    }

    public function destroy(WebsiteAssignment $websiteAssignment): RedirectResponse
    {
        Gate::authorize('update', $websiteAssignment->website);

        $websiteAssignment->delete();

        return back()->with('success', 'Member removed.');
    }
}
