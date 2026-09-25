<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsServiceInteraction;
use App\Models\Employee;
use App\Services\Cs\CsAccess;
use App\Services\Cs\CsServiceQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Customer Service Board Requirements Specification v1.0 §8 "Response time"
 * / "Resolution" — employee-logged enquiry lifecycle. An employee can only
 * log and update their own interactions; only App\Http\Controllers\Cs\CsComplaintController
 * and CsContactQualityReviewController are HOD-gated, since logging an
 * enquiry is ordinary daily work, not an approval action.
 */
class CsServiceInteractionController extends Controller
{
    public function store(Request $request, Employee $employee, CsServiceQualityService $service): RedirectResponse
    {
        abort_unless(CsAccess::owns($request->user(), $employee) && $request->user()->isCsEmployee(), 403, 'You can only log your own interactions.');

        $validated = $request->validate([
            'customer_identifier' => ['nullable', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:'.implode(',', CsServiceInteraction::CHANNELS)],
            'enquiry_received_at' => ['nullable', 'date'],
            'card_item_id' => ['nullable', 'integer', 'exists:cs_card_items,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['enquiry_received_at'] ??= now();

        $interaction = $service->logInteraction($employee, $validated);

        return back()->with('success', "Interaction logged (#{$interaction->id}).");
    }

    public function respond(Request $request, CsServiceInteraction $interaction, CsServiceQualityService $service): RedirectResponse
    {
        abort_unless($interaction->employee !== null && CsAccess::owns($request->user(), $interaction->employee), 403);

        $service->recordFirstResponse($interaction);

        return back()->with('success', 'First response recorded.');
    }

    public function resolve(Request $request, CsServiceInteraction $interaction, CsServiceQualityService $service): RedirectResponse
    {
        $employee = $interaction->employee;
        abort_unless($employee !== null && (CsAccess::owns($request->user(), $employee) || CsAccess::leadsEmployee($request->user(), $employee)), 403);

        $validated = $request->validate([
            'resolution_status' => ['required', 'string', 'in:'.implode(',', CsServiceInteraction::RESOLUTION_STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $service->updateResolution($interaction, $validated['resolution_status'], $validated['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['resolution_status' => $e->getMessage()]);
        }

        return back()->with('success', 'Resolution updated.');
    }
}
