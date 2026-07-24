<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\AuditLogger;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'canDeleteAccount' => config('auth.allow_account_deletion'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = $request->user();
            $old = $user->only(['name', 'email']);
            $user->fill($request->validated());

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $changes = $user->getDirty();
            $user->save();

            if (array_intersect_key($changes, array_flip(['name', 'email'])) !== []) {
                AuditLogger::log($user, 'profile_updated', $old, $user->only(['name', 'email']));
            }
        });

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(config('auth.allow_account_deletion'), 403, 'Account deletion is managed by an administrator.');

        $user = $request->user();

        Auth::logout();

        // Self-service closure means actually gone, not just hidden — unlike
        // Admin\UserController::destroy()'s soft delete (added once User became
        // soft-deletable), which stays reversible for admin-initiated removals.
        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
