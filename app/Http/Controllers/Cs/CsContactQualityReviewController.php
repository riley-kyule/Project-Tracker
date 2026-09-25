<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsServiceInteraction;
use App\Models\Employee;
use App\Services\Cs\CsAccess;
use App\Services\Cs\CsServiceQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Customer Service Board Requirements Specification v1.0 §8 "Contact quality" — HOD-only sampled review. */
class CsContactQualityReviewController extends Controller
{
    public function store(Request $request, Employee $employee, CsServiceQualityService $service): RedirectResponse
    {
        abort_unless(CsAccess::leadsEmployee($request->user(), $employee), 403);

        $validated = $request->validate([
            'service_interaction_id' => ['nullable', 'integer', 'exists:cs_service_interactions,id'],
            'channel' => ['nullable', 'string', 'max:50'],
            'accuracy_ok' => ['boolean'],
            'professionalism_ok' => ['boolean'],
            'policy_compliance_ok' => ['boolean'],
            'correct_advice_ok' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_if(
            ! empty($validated['service_interaction_id'])
                && ! CsServiceInteraction::query()->whereKey($validated['service_interaction_id'])->where('employee_id', $employee->id)->exists(),
            422,
            'That interaction belongs to a different employee.',
        );

        $service->reviewContact($employee, $validated, $request->user());

        return back()->with('success', 'Contact quality review recorded.');
    }
}
