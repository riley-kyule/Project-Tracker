<?php

namespace App\Http\Controllers\Testing;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Playwright-only login bypass. EWMS has no password login — Google SSO is
 * the only real sign-in method — so e2e tests need a way to establish an
 * authenticated session without driving a real OAuth flow.
 *
 * Registered only when both app()->environment(['local','testing']) AND
 * config('app.allow_e2e_login') are true (see routes/e2e.php); this method
 * re-checks the same two conditions itself so a future change to route
 * registration can't silently drop the guard. Never enable
 * ALLOW_E2E_LOGIN outside a throwaway environment the e2e suite owns.
 */
class E2eAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']) && config('app.allow_e2e_login'), 404);

        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }
}
