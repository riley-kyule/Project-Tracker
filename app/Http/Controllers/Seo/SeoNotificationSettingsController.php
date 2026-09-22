<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Department Notification Settings — SEO Board Requirements Specification
 * v1.1 §4.2.1: HOD email is the department's manager_id (edited on the
 * existing department record, linked here rather than duplicated);
 * additional recipient emails are configurable here without a code change,
 * effective-dated, and audited. Viewed on the "SEO Board Settings" hub
 * (SeoSettingsController); this controller is action-only.
 */
class SeoNotificationSettingsController extends Controller
{
    public function store(Request $request, Department $department): RedirectResponse
    {
        abort_unless($request->user()->can('seo.settings.manage'), 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $recipient = DepartmentNotificationRecipient::query()->create([
            'department_id' => $department->id,
            'email' => $validated['email'],
            'label' => $validated['label'] ?? null,
            'is_active' => true,
            'effective_from' => now(),
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log($recipient, 'department_notification_recipient.added', [], $recipient->only(['email', 'label']));

        return back()->with('success', 'Recipient added.');
    }

    public function destroy(Request $request, DepartmentNotificationRecipient $recipient): RedirectResponse
    {
        abort_unless($request->user()->can('seo.settings.manage'), 403);

        $old = $recipient->only(['email', 'label', 'is_active']);
        $recipient->update(['is_active' => false, 'effective_to' => now()]);

        AuditLogger::log($recipient, 'department_notification_recipient.deactivated', $old, $recipient->only(['is_active', 'effective_to']));

        return back()->with('success', 'Recipient removed.');
    }
}
