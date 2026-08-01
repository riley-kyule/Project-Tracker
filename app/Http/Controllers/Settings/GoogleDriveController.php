<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Services\AuditLogger;
use App\Services\GoogleWorkspaceDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * A separate OAuth consent flow from the login SSO in GoogleAuthController —
 * this one asks for the Drive scope plus offline access (a refresh token) so
 * scheduled backups can run without an admin present, reusing the same
 * Google OAuth client credentials rather than needing a second one.
 */
class GoogleDriveController extends Controller
{
    private const SCOPES = ['https://www.googleapis.com/auth/drive.file'];

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);
        abort_unless(config('services.google.client_id'), 404);

        return Socialite::driver('google')
            ->scopes(self::SCOPES)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirectUrl(route('integrations.google-drive.callback'))
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);
        abort_unless(config('services.google.client_id'), 404);

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('integrations.google-drive.callback'))
                ->user();
        } catch (Throwable $e) {
            return redirect()->route('integrations.edit')->withErrors([
                'drive' => 'Google Drive connection failed. Please try again.',
            ]);
        }

        if (! GoogleWorkspaceDomain::isAllowed($googleUser->getEmail())) {
            return redirect()->route('integrations.edit')->withErrors([
                'drive' => 'Backups must be connected to a company Google account.',
            ]);
        }

        if (! $googleUser->refreshToken) {
            // Google only issues a refresh token on first-ever consent for this
            // app+account (or after the account owner revokes prior access) —
            // silently missing it here means a stale authorization is still on
            // file at Google, not a real failure the admin can retry their way out of.
            return redirect()->route('integrations.edit')->withErrors([
                'drive' => 'Google did not grant offline access. Remove EWMS from this account\'s '.
                    'connected apps at myaccount.google.com/permissions, then try connecting again.',
            ]);
        }

        $settings = CompanySetting::current();
        $settings->update([
            'google_drive_connected_email' => $googleUser->getEmail(),
            'google_drive_access_token' => $googleUser->token,
            'google_drive_refresh_token' => $googleUser->refreshToken,
            'google_drive_token_expires_at' => now()->addSeconds($googleUser->expiresIn ?? 3600),
            'google_drive_folder_id' => null,
        ]);

        AuditLogger::log($settings, 'google_drive_connected', [], ['email' => $googleUser->getEmail()]);

        return redirect()->route('integrations.edit')->with('success', 'Google Drive connected.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        $settings = CompanySetting::current();
        $email = $settings->google_drive_connected_email;

        $settings->update([
            'google_drive_connected_email' => null,
            'google_drive_access_token' => null,
            'google_drive_refresh_token' => null,
            'google_drive_token_expires_at' => null,
            'google_drive_folder_id' => null,
        ]);

        AuditLogger::log($settings, 'google_drive_disconnected', ['email' => $email], []);

        return redirect()->route('integrations.edit')->with('success', 'Google Drive disconnected.');
    }
}
