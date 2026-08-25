<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WordPressUser;
use App\Services\WordPress\WordPressUserBulkAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Every action authorizes once up front (no per-row ownership model for
 * WordPress users, unlike tasks/boards) and reports per-row/per-site results
 * back to the frontend as `bulkResults` — a batch spanning many external
 * sites failing partway through is the expected common case, not the
 * exception, so it's always surfaced rather than collapsed into one flash
 * message.
 */
class WordPressUserBulkActionController extends Controller
{
    public function add(Request $request, WordPressUserBulkAction $bulkAction): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        $validated = $request->validate([
            'website_ids' => ['required', 'array', 'min:1'],
            'website_ids.*' => ['integer', 'exists:websites,id'],
            'username' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
        ]);

        $results = $bulkAction->add($validated['website_ids'], $validated['username'], $validated['email'], $validated['password'], $validated['roles']);

        return back()->with(['success' => $this->summarize($results), 'bulkResults' => $results]);
    }

    public function changeRole(Request $request, WordPressUserBulkAction $bulkAction): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        $validated = $request->validate([
            'wordpress_user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'wordpress_user_ids.*' => ['integer', 'exists:wordpress_users,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
        ]);

        $users = WordPressUser::query()->whereIn('id', $validated['wordpress_user_ids'])->get();
        $results = $bulkAction->changeRole($users, $validated['roles']);

        return back()->with(['success' => $this->summarize($results), 'bulkResults' => $results]);
    }

    public function updateEmail(Request $request, WordPressUserBulkAction $bulkAction): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        $validated = $request->validate([
            'updates' => ['required', 'array', 'min:1', 'max:500'],
            'updates.*.id' => ['required', 'integer', 'exists:wordpress_users,id'],
            'updates.*.email' => ['required', 'email', 'max:255'],
        ]);

        $users = WordPressUser::query()->whereIn('id', collect($validated['updates'])->pluck('id'))->get();
        $results = $bulkAction->updateEmail($users, $validated['updates']);

        return back()->with(['success' => $this->summarize($results), 'bulkResults' => $results]);
    }

    public function destroy(Request $request, WordPressUserBulkAction $bulkAction): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        $validated = $request->validate([
            'wordpress_user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'wordpress_user_ids.*' => ['integer', 'exists:wordpress_users,id'],
        ]);

        $users = WordPressUser::query()->whereIn('id', $validated['wordpress_user_ids'])->get();
        $results = $bulkAction->delete($users);

        return back()->with(['success' => $this->summarize($results), 'bulkResults' => $results]);
    }

    private function summarize(array $results): string
    {
        $succeeded = collect($results)->where('status', 'ok')->count();
        $failed = count($results) - $succeeded;

        return $failed > 0
            ? "{$succeeded} succeeded, {$failed} failed."
            : "{$succeeded} succeeded.";
    }
}
