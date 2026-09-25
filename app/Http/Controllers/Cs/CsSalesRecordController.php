<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsSalesRecord;
use App\Models\Employee;
use App\Services\Cs\CsSalesAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The commercial ledger's employee/HOD actions — Customer Service Board
 * Requirements Specification v1.0 §3/§4/§5.1. An employee records a claimed
 * sale with its evidence; only the HOD clears it (moving it into the
 * counted set) or flags it reversed/refunded/fraudulent.
 */
class CsSalesRecordController extends Controller
{
    public function store(Request $request, Employee $employee, CsSalesAttributionService $attribution): RedirectResponse
    {
        Gate::authorize('create', CsSalesRecord::class);
        abort_unless($employee->user_id === $request->user()->id, 403, 'You can only record sales against your own record.');

        $validated = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', CsSalesRecord::CATEGORIES)],
            'customer_identifier' => ['required', 'string', 'max:255'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'country' => ['nullable', 'string', 'max:100'],
            'contact_channel' => ['nullable', 'string', 'max:100'],
            'contact_time' => ['nullable', 'date'],
            'payment_reference' => ['required', 'string', 'max:255', 'unique:cs_sales_records,payment_reference'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'week_start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $record = $attribution->record($employee, $validated, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['customer_identifier' => $e->getMessage()]);
        }

        return back()->with('success', "Sale recorded (#{$record->id}) — pending HOD clearance.");
    }

    public function clear(Request $request, CsSalesRecord $record, CsSalesAttributionService $attribution): RedirectResponse
    {
        Gate::authorize('approve', $record);

        $validated = $request->validate([
            'attribution_type' => ['nullable', 'string', 'in:primary,shared'],
            'shared_with_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'split_percentage' => ['nullable', 'numeric', 'min:1', 'max:99'],
        ]);

        if (($validated['attribution_type'] ?? null) === 'shared' && empty($validated['shared_with_employee_id'])) {
            throw ValidationException::withMessages(['shared_with_employee_id' => 'Select who this sale is shared with.']);
        }

        $attribution->clear($record, $request->user(), $validated);

        return back()->with('success', 'Sale cleared — it now counts toward the weekly target.');
    }

    public function flag(Request $request, CsSalesRecord $record, CsSalesAttributionService $attribution): RedirectResponse
    {
        Gate::authorize('approve', $record);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:reversed,refunded,fraudulent'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $attribution->flag($record, $validated['status'], $validated['reason'], $request->user());

        return back()->with('success', 'Sale flagged — it no longer counts as collected revenue.');
    }
}
