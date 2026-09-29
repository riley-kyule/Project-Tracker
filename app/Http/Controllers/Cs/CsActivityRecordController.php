<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsActivityRecord;
use App\Models\CsSalesRecord;
use App\Models\Employee;
use App\Services\Cs\CsAccess;
use App\Services\Cs\CsActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Customer Service Board Requirements Specification v1.0 §5.1 — an employee
 * logs and amends their own daily sales activity. Not HOD-gated: recording
 * a contact attempt is routine daily work, the same reasoning as
 * CsServiceInteractionController.
 */
class CsActivityRecordController extends Controller
{
    public function store(Request $request, Employee $employee, CsActivityLogService $service): RedirectResponse
    {
        abort_unless(CsAccess::owns($request->user(), $employee) && $request->user()->isCsEmployee(), 403, 'You can only log your own activity.');

        $validated = $request->validate([
            'workstream' => ['required', 'string', 'in:'.implode(',', CsActivityRecord::WORKSTREAMS)],
            'customer_identifier' => ['required', 'string', 'max:255'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'country' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'in:'.implode(',', CsActivityRecord::CHANNELS)],
            'contact_time' => ['nullable', 'date'],
            'stage' => ['nullable', 'string', 'in:'.implode(',', CsActivityRecord::STAGES)],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'registration_status' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'contact_attempts' => ['nullable', 'integer', 'min:0'],
            'response' => ['nullable', 'string', 'max:2000'],
            'reason_for_non_renewal' => ['nullable', 'string', 'max:2000'],
            'renewal_status' => ['nullable', 'string', 'max:255'],
            'inactive_period_days' => ['nullable', 'integer', 'min:0'],
            'restored_service' => ['nullable', 'boolean'],
            'issue' => ['nullable', 'string', 'max:2000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_provider' => ['nullable', 'string', 'max:255'],
            'escalation_owner' => ['nullable', 'string', 'max:255'],
            'resolution' => ['nullable', 'string', 'max:2000'],
            'activation_confirmed' => ['nullable', 'boolean'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'sales_record_id' => ['nullable', 'integer', 'exists:cs_sales_records,id'],
        ]);

        if (! empty($validated['sales_record_id'])) {
            $owned = CsSalesRecord::query()->whereKey($validated['sales_record_id'])->where('employee_id', $employee->id)->exists();
            abort_unless($owned, 422, 'That sale belongs to a different employee.');
        }

        try {
            $record = $service->log($employee, $validated, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['workstream' => $e->getMessage()]);
        }

        return back()->with('success', "Activity logged (#{$record->id}).");
    }

    public function update(Request $request, CsActivityRecord $record, CsActivityLogService $service): RedirectResponse
    {
        abort_unless($record->employee !== null && CsAccess::owns($request->user(), $record->employee), 403);

        $validated = $request->validate([
            'stage' => ['nullable', 'string', 'in:'.implode(',', CsActivityRecord::STAGES)],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'response' => ['nullable', 'string', 'max:2000'],
            'renewal_status' => ['nullable', 'string', 'max:255'],
            'reason_for_non_renewal' => ['nullable', 'string', 'max:2000'],
            'registration_status' => ['nullable', 'string', 'max:255'],
            'restored_service' => ['nullable', 'boolean'],
            'resolution' => ['nullable', 'string', 'max:2000'],
            'activation_confirmed' => ['nullable', 'boolean'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $service->update($record, $validated);

        return back()->with('success', 'Activity updated.');
    }
}
