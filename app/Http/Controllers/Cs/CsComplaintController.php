<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsComplaint;
use App\Models\CsServiceInteraction;
use App\Models\Employee;
use App\Services\Cs\CsAccess;
use App\Services\Cs\CsServiceQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Customer Service Board Requirements Specification v1.0 §8 "Customer
 * complaints" — HOD-controlled end to end, since a complaint is inherently
 * a finding about an employee's work, not something they self-report. The
 * "no self-scoring" rule extends here: an employee can never substantiate
 * or dismiss a complaint against themselves.
 */
class CsComplaintController extends Controller
{
    public function store(Request $request, Employee $employee, CsServiceQualityService $service): RedirectResponse
    {
        abort_unless(CsAccess::leadsEmployee($request->user(), $employee), 403);

        $validated = $request->validate([
            'service_interaction_id' => ['nullable', 'integer', 'exists:cs_service_interactions,id'],
            'description' => ['required', 'string', 'max:2000'],
            'reported_at' => ['nullable', 'date'],
        ]);

        $this->assertInteractionBelongsTo($employee, $validated['service_interaction_id'] ?? null);

        $validated['reported_at'] ??= now();

        $complaint = $service->logComplaint($employee, $validated, $request->user());

        return back()->with('success', "Complaint logged (#{$complaint->id}).");
    }

    private function assertInteractionBelongsTo(Employee $employee, ?int $interactionId): void
    {
        abort_if(
            $interactionId !== null && ! CsServiceInteraction::query()->whereKey($interactionId)->where('employee_id', $employee->id)->exists(),
            422,
            'That interaction belongs to a different employee.',
        );
    }

    public function decide(Request $request, CsComplaint $complaint, CsServiceQualityService $service): RedirectResponse
    {
        abort_unless($complaint->employee !== null && CsAccess::leadsEmployee($request->user(), $complaint->employee), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:substantiated,unsubstantiated,resolved'],
            'decision' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $service->decideComplaint($complaint, $validated['status'], $validated['decision'], $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Complaint decision recorded.');
    }
}
