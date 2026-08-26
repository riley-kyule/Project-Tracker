<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWordPressUsersForSite;
use App\Models\WordPressCredential;
use App\Models\WordPressSite;
use App\Services\AuditLogger;
use App\Services\WordPress\WordPressUserClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Connect a website" is a single step on the WordPress Users page: a site's
 * name/domain and its Application Password credentials are entered together,
 * not across two separate forms — see store() below.
 */
class WordPressSiteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'unique:wordpress_sites,domain'],
            'wp_username' => ['required', 'string', 'max:255'],
            'wp_app_password' => ['required', 'string', 'max:255'],
        ]);

        $site = WordPressSite::create([
            'name' => $validated['name'],
            'domain' => $validated['domain'],
        ]);

        $credential = $site->credential()->create([
            'wp_username' => $validated['wp_username'],
            'wp_app_password' => $validated['wp_app_password'],
        ]);

        AuditLogger::log($credential, 'created', [], ['wordpress_site_id' => $site->id, 'wp_username' => $credential->wp_username]);

        // "Connect" means pulling in its user roster right away, not waiting
        // for the next scheduled sync or a separate manual click.
        SyncWordPressUsersForSite::dispatch($credential->id);

        return back()->with('success', "Connected {$site->name} — syncing its users now.");
    }

    public function update(Request $request, WordPressSite $site): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'unique:wordpress_sites,domain,'.$site->id],
            'wp_username' => ['required', 'string', 'max:255'],
            'wp_app_password' => ['nullable', 'string', 'max:255'],
        ]);

        $site->update(['name' => $validated['name'], 'domain' => $validated['domain']]);

        $credential = $site->credential;
        $attrs = ['wp_username' => $validated['wp_username']];

        // A blank password field means "leave it as it is" — the current value is
        // never sent back to the browser to be round-tripped, so an empty
        // submission can't be distinguished from "clear it" otherwise.
        if (filled($validated['wp_app_password'] ?? null)) {
            $attrs['wp_app_password'] = $validated['wp_app_password'];
        }

        $old = $credential->only(['wp_username']);
        $credential->update($attrs);
        AuditLogger::log($credential, 'updated', $old, $credential->only(['wp_username']));

        return back()->with('success', "{$site->name} updated.");
    }

    public function destroy(Request $request, WordPressSite $site): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        AuditLogger::log($site, 'deleted', ['name' => $site->name, 'domain' => $site->domain], []);

        // wordpress_credentials and wordpress_users both cascade on delete via
        // their wordpress_site_id FK, so removing the site clears everything.
        $site->delete();

        return back()->with('success', "{$site->name} disconnected.");
    }

    public function test(Request $request, WordPressSite $site): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);
        $credential = $site->credential()->firstOrFail();

        $result = (new WordPressUserClient($credential))->verifyCredentials();

        $credential->update($result['status'] === 'ok'
            ? ['status' => WordPressCredential::STATUS_OK, 'last_verified_at' => now(), 'last_error' => null]
            : ['status' => WordPressCredential::STATUS_ERROR, 'last_error' => $result['error']]);

        return back()->with(
            $result['status'] === 'ok' ? 'success' : 'error',
            $result['status'] === 'ok' ? "Connection to {$site->name} verified." : "Connection to {$site->name} failed: {$result['error']}",
        );
    }

    public function sync(Request $request, WordPressSite $site): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);
        $credential = $site->credential()->firstOrFail();

        SyncWordPressUsersForSite::dispatch($credential->id);

        return back()->with('success', "Sync queued for {$site->name}.");
    }
}
