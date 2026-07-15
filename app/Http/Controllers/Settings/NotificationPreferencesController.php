<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferencesController extends Controller
{
    /** Every notification type any part of the app dispatches — see User::wantsNotification(). */
    public const TYPES = [
        'task_assigned', 'task_commented', 'comment_mention', 'task_due_soon', 'task_overdue',
        'task_blocked', 'task_collaborator_added', 'task_approval_requested', 'task_approval_decided', 'recurrence_missed',
        'ticket_submitted', 'ticket_assigned', 'ticket_updated', 'ticket_overdue', 'ticket_response_overdue',
        'analytics_source_stale',
    ];

    public function edit(Request $request): Response
    {
        $preferences = $request->user()->notification_preferences ?? [];

        return Inertia::render('settings/notifications', [
            'preferences' => collect(self::TYPES)->mapWithKeys(fn ($type) => [$type => ($preferences[$type] ?? true) !== false]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ]);

        $preferences = [];
        foreach (self::TYPES as $type) {
            $preferences[$type] = (bool) ($validated['preferences'][$type] ?? true);
        }

        $request->user()->update(['notification_preferences' => $preferences]);

        return back();
    }
}
