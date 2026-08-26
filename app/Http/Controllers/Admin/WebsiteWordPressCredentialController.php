<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWordPressUsersForWebsite;
use App\Models\Website;
use App\Models\WebsiteWordPressCredential;
use App\Models\WordPressUser;
use App\Services\AuditLogger;
use App\Services\WordPress\WordPressUserClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Credential fields themselves (username/password) are created and edited
 * from the website's own form — see Admin\WebsiteController::syncWordPressCredential().
 * This controller only holds the actions that don't fit that create/update
 * shape: verifying a connection, triggering a sync, and removing a credential.
 */
class WebsiteWordPressCredentialController extends Controller
{
    public function test(Request $request, Website $website): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);
        $credential = $website->wordpressCredential()->firstOrFail();

        $result = (new WordPressUserClient($credential))->verifyCredentials();

        $credential->update($result['status'] === 'ok'
            ? ['status' => WebsiteWordPressCredential::STATUS_OK, 'last_verified_at' => now(), 'last_error' => null]
            : ['status' => WebsiteWordPressCredential::STATUS_ERROR, 'last_error' => $result['error']]);

        return back()->with(
            $result['status'] === 'ok' ? 'success' : 'error',
            $result['status'] === 'ok' ? "Connection to {$website->name} verified." : "Connection to {$website->name} failed: {$result['error']}",
        );
    }

    public function sync(Request $request, Website $website): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);
        $credential = $website->wordpressCredential()->firstOrFail();

        SyncWordPressUsersForWebsite::dispatch($credential->id);

        return back()->with('success', "Sync queued for {$website->name}.");
    }

    public function destroy(Request $request, Website $website): RedirectResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);
        $credential = $website->wordpressCredential()->firstOrFail();

        AuditLogger::log($credential, 'deleted', ['website_id' => $website->id, 'wp_username' => $credential->wp_username], []);

        // No FK relationship between the two tables (both independently
        // reference websites.id), so the cached user rows don't cascade
        // automatically — clear them explicitly along with the credential.
        WordPressUser::query()->where('website_id', $website->id)->delete();
        $credential->delete();

        return back()->with('success', "WordPress credentials removed for {$website->name}.");
    }
}
