<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoCardItem;
use App\Services\AuditLogger;
use App\Services\Seo\SeoScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Item-level status, evidence, and HOD decisions — SEO Board Requirements
 * Specification v1.1 §6/§8. Every user-correctable rejection here is a
 * ValidationException, never a bare abort(422) — Inertia treats a non-Inertia
 * error response (which a plain abort produces) as an unexpected server error
 * and renders it full-screen, blocking the page, instead of the inline
 * per-field error a ValidationException gives it.
 */
class SeoCardItemController extends Controller
{
    /** Employee: status, comment, self-reported quantity/blocker — never a final score (§13 "no self-scoring"). */
    public function updateStatus(Request $request, SeoCardItem $item, SeoScoringService $scoring): RedirectResponse
    {
        Gate::authorize('update', $item);

        $validated = $request->validate([
            'employee_status' => ['required', 'string', 'in:'.implode(',', SeoCardItem::EMPLOYEE_STATUSES)],
            'employee_comment' => ['nullable', 'string', 'max:5000'],
            'achieved_quantity' => ['nullable', 'numeric', 'min:0'],
            'blocker_reason' => ['nullable', 'string', 'max:1000'],
            'escalated_to' => ['nullable', 'string', 'max:255'],
        ]);

        if ($item->evidence_required && $validated['employee_status'] === SeoCardItem::STATUS_SUBMITTED && $item->evidence()->count() === 0) {
            throw ValidationException::withMessages(['employee_status' => 'Evidence is required before this item can be submitted.']);
        }

        if ($validated['employee_status'] === SeoCardItem::STATUS_BLOCKED && empty($validated['blocker_reason'])) {
            throw ValidationException::withMessages(['blocker_reason' => 'A blocker reason is required.']);
        }

        if ($validated['employee_status'] === SeoCardItem::STATUS_BLOCKED) {
            $validated['escalated_at'] = now();
        }

        $old = $item->only(array_keys($validated));
        $validated['submitted_at'] = $validated['employee_status'] === SeoCardItem::STATUS_SUBMITTED ? now() : $item->submitted_at;
        $item->update($validated);

        AuditLogger::log($item, 'seo_item.status_updated', $old, $item->only(array_keys($validated)));

        $scoring->recalculateEmployeeSubmitted($item->cardable);

        return back();
    }

    /** HOD decision — the only path that can ever set earned_points, per §13 "no self-scoring". */
    public function decide(Request $request, SeoCardItem $item, SeoScoringService $scoring): RedirectResponse
    {
        Gate::authorize('approve', $item);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:'.implode(',', SeoCardItem::DECISIONS)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'accepted_quantity' => ['nullable', 'numeric', 'min:0'],
            'available_target_quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $scoring->decide(
                $item,
                $validated['decision'],
                $validated['reason'] ?? null,
                $request->user(),
                $validated['accepted_quantity'] ?? null,
                $validated['available_target_quantity'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Decision recorded.');
    }

    public function updateWeight(Request $request, SeoCardItem $item, SeoScoringService $scoring): RedirectResponse
    {
        Gate::authorize('approve', $item);

        $validated = $request->validate([
            'weight' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $scoring->updateWeight($item, (float) $validated['weight'], $validated['reason']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Weight updated.');
    }

    public function updateTarget(Request $request, SeoCardItem $item, SeoScoringService $scoring): RedirectResponse
    {
        Gate::authorize('approve', $item);

        $validated = $request->validate([
            'target_quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $scoring->updateTarget($item, (float) $validated['target_quantity'], $validated['reason']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Target updated.');
    }
}
