<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google's OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 404);

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    /**
     * Handle Google's callback: verify the account's email domain against
     * the allow-list, then log in an existing user or provision a new one.
     *
     * New accounts are created bare (no role or department) so they remain
     * harmless until an administrator assigns access via /admin/users.
     */
    public function callback(): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google sign-in failed. Please try again.',
            ]);
        }

        if (! $this->domainIsAllowed($googleUser)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Sign-in is restricted to company Google accounts.',
            ]);
        }

        $user = $this->findOrProvisionUser($googleUser);

        if (! $user->isActive()) {
            return redirect()->route('login')->withErrors([
                'email' => __('auth.failed'),
            ]);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function domainIsAllowed(SocialiteUser $googleUser): bool
    {
        $allowedDomains = config('services.google.allowed_domains', []);

        if (empty($allowedDomains)) {
            return false;
        }

        $domain = Str::lower(Str::after($googleUser->getEmail(), '@'));

        return in_array($domain, array_map(Str::lower(...), $allowedDomains), true);
    }

    private function findOrProvisionUser(SocialiteUser $googleUser): User
    {
        $email = Str::lower($googleUser->getEmail());

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            if (! $user->google_id) {
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }

            return $user;
        }

        $user = User::create([
            'name' => $googleUser->getName() ?: $email,
            'email' => $email,
            'google_id' => $googleUser->getId(),
            'password' => null,
            'status' => User::STATUS_ACTIVE,
        ]);

        AuditLogger::log($user, 'created', [], $user->only(['name', 'email']) + ['source' => 'google_sso']);

        return $user;
    }
}
