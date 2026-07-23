<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        $settings = CompanySetting::current();

        return Inertia::render('settings/integrations', [
            'settings' => [
                'mail_mailer' => $settings->mail_mailer,
                'mail_host' => $settings->mail_host,
                'mail_port' => $settings->mail_port,
                'mail_username' => $settings->mail_username,
                'mail_password_set' => filled($settings->mail_password),
                'mail_encryption' => $settings->mail_encryption,
                'mail_from_address' => $settings->mail_from_address,
                'mail_from_name' => $settings->mail_from_name,
                'epe_api_url' => $settings->epe_api_url,
                'epe_site_key' => $settings->epe_site_key,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        $validated = $request->validate([
            'mail_mailer' => ['nullable', 'in:log,smtp'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'in:tls,ssl'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'epe_api_url' => ['nullable', 'url', 'max:255'],
            'epe_site_key' => ['nullable', 'string', 'max:255'],
        ]);

        // A blank password field means "leave it as it is" — the current value
        // is never sent back to the browser to be round-tripped, so an empty
        // submission can't be distinguished from "clear it" otherwise.
        if (blank($validated['mail_password'] ?? null)) {
            unset($validated['mail_password']);
        }

        $settings = CompanySetting::current();
        $old = $settings->only(array_keys($validated));
        unset($old['mail_password']);
        $settings->update($validated);

        $new = $settings->only(array_keys($validated));
        unset($new['mail_password']);
        AuditLogger::log($settings, 'updated', $old, $new);

        // Long-running queue workers only read CompanySetting once, at boot —
        // restart them so a saved mail change takes effect without a full deploy.
        Artisan::call('queue:restart');

        return back()->with('success', 'Integration settings updated.');
    }
}
