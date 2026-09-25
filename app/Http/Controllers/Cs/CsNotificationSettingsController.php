<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Services\AuditLogger;
use App\Services\Cs\CsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer Service Board Notification Settings — Customer Service Board
 * Requirements Specification v1.0 §11: HOD email is the department's
 * manager_id (edited on the existing department record); additional
 * recipient emails are configurable here without a code change,
 * effective-dated, and audited. Mirrors
 * App\Http\Controllers\Seo\SeoNotificationSettingsController — reuses
 * department_notification_recipients directly.
 */
class CsNotificationSettingsController extends Controller
{
    public function store(Request $request, Department $department): RedirectResponse
    {
        abort_unless($request->user()->can('cs.settings.manage') && CsAccess::leadsBoard($request->user()), 403);
        // Recipients here only ever belong to the Customer Service department.
        abort_unless($department->id === CsAccess::department()?->id, 404);

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
        abort_unless($request->user()->can('cs.settings.manage') && CsAccess::leadsBoard($request->user()), 403);
        abort_unless($recipient->department_id === CsAccess::department()?->id, 404);

        $old = $recipient->only(['email', 'label', 'is_active']);
        $recipient->update(['is_active' => false, 'effective_to' => now()]);

        AuditLogger::log($recipient, 'department_notification_recipient.deactivated', $old, $recipient->only(['is_active', 'effective_to']));

        return back()->with('success', 'Recipient removed.');
    }
}
