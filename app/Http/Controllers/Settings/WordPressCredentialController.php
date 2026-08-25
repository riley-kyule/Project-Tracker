<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWordPressUsersForWebsite;
use App\Models\Website;
use App\Models\WebsiteWordPressCredential;
use App\Models\WordPressUser;
use App\Services\AuditLogger;
use App\Services\WordPress\WordPressUserClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WordPressCredentialController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage', WebsiteWordPressCredential::class);

        return Inertia::render('settings/integrations-wordpress', [
            'websites' => Website::query()
                ->with('wordpressCredential')
                ->orderBy('name')
                ->get()
                ->map(fn (Website $website) => [
                    'id' => $website->id,
                    'name' => $website->name,
                    'domain' => $website->domain,
                    'credential' => $website->wordpressCredential ? [
                        'id' => $website->wordpressCredential->id,
                        'wp_username' => $website->wordpressCredential->wp_username,
                        'wp_app_password_set' => filled($website->wordpressCredential->wp_app_password),
                        'status' => $website->wordpressCredential->status,
                        'last_verified_at' => $website->wordpressCredential->last_verified_at,
                        'last_synced_at' => $website->wordpressCredential->last_synced_at,
                        'last_error' => $website->wordpressCredential->last_error,
                    ] : null,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', WebsiteWordPressCredential::class);

        $validated = $request->validate([
            'website_id' => ['required', 'integer', 'exists:websites,id', 'unique:website_wordpress_credentials,website_id'],
            'wp_username' => ['required', 'string', 'max:255'],
            'wp_app_password' => ['required', 'string', 'max:255'],
        ]);

        $credential = WebsiteWordPressCredential::create($validated);

        AuditLogger::log($credential, 'created', [], ['website_id' => $credential->website_id, 'wp_username' => $credential->wp_username]);

        return back()->with('success', 'WordPress credentials added.');
    }

    public function update(Request $request, WebsiteWordPressCredential $credential): RedirectResponse
    {
        Gate::authorize('manage', WebsiteWordPressCredential::class);

        $validated = $request->validate([
            'wp_username' => ['required', 'string', 'max:255'],
            'wp_app_password' => ['nullable', 'string', 'max:255'],
        ]);

        // A blank password field means "leave it as it is" — the current value is
        // never sent back to the browser to be round-tripped, so an empty
        // submission can't be distinguished from "clear it" otherwise.
        if (blank($validated['wp_app_password'] ?? null)) {
            unset($validated['wp_app_password']);
        }

        $old = $credential->only(['wp_username']);
        $credential->update($validated);

        AuditLogger::log($credential, 'updated', $old, $credential->only(['wp_username']));

        return back()->with('success', 'WordPress credentials updated.');
    }

    public function destroy(WebsiteWordPressCredential $credential): RedirectResponse
    {
        Gate::authorize('manage', WebsiteWordPressCredential::class);

        AuditLogger::log($credential, 'deleted', ['website_id' => $credential->website_id, 'wp_username' => $credential->wp_username], []);

        // No FK relationship between the two tables (both independently
        // reference websites.id), so the cached user rows don't cascade
        // automatically — clear them explicitly along with the credential.
        WordPressUser::query()->where('website_id', $credential->website_id)->delete();
        $credential->delete();

        return back()->with('success', 'WordPress credentials removed.');
    }

    public function test(WebsiteWordPressCredential $credential): RedirectResponse
    {
        Gate::authorize('manage', WebsiteWordPressCredential::class);

        $result = (new WordPressUserClient($credential))->verifyCredentials();

        $credential->update($result['status'] === 'ok'
            ? ['status' => WebsiteWordPressCredential::STATUS_OK, 'last_verified_at' => now(), 'last_error' => null]
            : ['status' => WebsiteWordPressCredential::STATUS_ERROR, 'last_error' => $result['error']]);

        return back()->with(
            $result['status'] === 'ok' ? 'success' : 'error',
            $result['status'] === 'ok' ? 'Connection verified.' : "Connection failed: {$result['error']}",
        );
    }

    public function sync(WebsiteWordPressCredential $credential): RedirectResponse
    {
        Gate::authorize('manage', WebsiteWordPressCredential::class);

        SyncWordPressUsersForWebsite::dispatch($credential->id);

        return back()->with('success', 'Sync queued.');
    }
}
