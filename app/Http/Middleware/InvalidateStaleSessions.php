<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces re-authentication if an admin changed this user's role or status
 * after the current session was established — without this, an
 * already-open session keeps whatever access it started with until it
 * naturally expires (SESSION_LIFETIME, 120 minutes), no matter what changed
 * server-side in the meantime.
 */
class InvalidateStaleSessions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->sessions_invalidated_at && $this->sessionPredatesInvalidation($request, $user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account access was updated. Please sign in again.',
            ]);
        }

        return $next($request);
    }

    private function sessionPredatesInvalidation(Request $request, User $user): bool
    {
        $authenticatedAt = $request->session()->get('authenticated_at');

        // No marker at all (a session started before this feature existed, or
        // one that somehow lost it) is treated as stale — safer to require a
        // fresh login than to assume the session is still fine.
        if ($authenticatedAt === null) {
            return true;
        }

        return $user->sessions_invalidated_at->timestamp > $authenticatedAt;
    }
}
