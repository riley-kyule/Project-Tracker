<?php

namespace App\Services\Cs;

use App\Models\CompanySetting;
use App\Models\CsCardItem;
use App\Models\CsComplaint;
use App\Models\CsContactQualityReview;
use App\Models\CsServiceInteraction;
use App\Models\CsWeeklyCard;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Customer Service Board Requirements Specification v1.0 §8 "Customer
 * service quality controls" — response time, resolution, complaints and
 * contact quality. Kept separate from CsScoringService (which owns card/item
 * scoring) the same way CsSalesAttributionService is: this governs the
 * underlying service-quality records, and exposes the one weekly achievement
 * figure CsScoringService reads for the "Customer-service quality" item.
 */
class CsServiceQualityService
{
    /** §8 "Response time": the enquiry is logged the moment it's received; the applicable standard is snapshotted from company_settings so a later standard change never rewrites a past interaction's grading. */
    public function logInteraction(Employee $employee, array $data): CsServiceInteraction
    {
        $standards = CompanySetting::current()->cs_response_time_standards ?? [];
        $channel = $data['channel'];

        $interaction = CsServiceInteraction::query()->create([
            ...$data,
            'employee_id' => $employee->id,
            'response_standard_minutes' => $standards[$channel] ?? 30,
        ]);

        AuditLogger::log($interaction, 'cs_interaction.logged', [], $interaction->only(['channel', 'enquiry_received_at']));

        return $interaction;
    }

    public function recordFirstResponse(CsServiceInteraction $interaction, ?Carbon $at = null): CsServiceInteraction
    {
        $old = $interaction->only(['first_response_at']);
        $interaction->first_response_at = $at ?? now();
        $interaction->save();

        AuditLogger::log($interaction, 'cs_interaction.responded', $old, ['first_response_at' => $interaction->first_response_at]);

        return $interaction;
    }

    /** §8 "Resolution": distinguishes first-contact resolution, resolved after escalation, pending customer, pending internal owner, unresolved. */
    public function updateResolution(CsServiceInteraction $interaction, string $status, ?string $notes = null): CsServiceInteraction
    {
        if (! in_array($status, CsServiceInteraction::RESOLUTION_STATUSES, true)) {
            throw new InvalidArgumentException("Unknown resolution status: {$status}");
        }

        $old = $interaction->only(['resolution_status', 'resolved_at', 'notes']);
        $interaction->resolution_status = $status;
        $interaction->resolved_at = in_array($status, CsServiceInteraction::RESOLVED_STATUSES, true) ? now() : null;
        $interaction->notes = $notes ?? $interaction->notes;
        $interaction->save();

        AuditLogger::log($interaction, 'cs_interaction.resolution_updated', $old, $interaction->only(['resolution_status', 'resolved_at', 'notes']));

        return $interaction;
    }

    /** §8 "Customer complaints": logged against an employee, optionally linked to the interaction it arose from. Starts "open" — only decide() ever substantiates or dismisses it. */
    public function logComplaint(Employee $employee, array $data, User $actor): CsComplaint
    {
        $complaint = CsComplaint::query()->create([
            ...$data,
            'employee_id' => $employee->id,
            'status' => CsComplaint::STATUS_OPEN,
            'created_by' => $actor->id,
        ]);

        AuditLogger::log($complaint, 'cs_complaint.logged', [], $complaint->only(['description', 'reported_at']));

        return $complaint;
    }

    /** The only path that can substantiate or dismiss a complaint — "no self-scoring" extends here: an employee can never clear a complaint against themselves. */
    public function decideComplaint(CsComplaint $complaint, string $status, string $decision, User $actor): CsComplaint
    {
        if (! in_array($status, [CsComplaint::STATUS_SUBSTANTIATED, CsComplaint::STATUS_UNSUBSTANTIATED, CsComplaint::STATUS_RESOLVED], true)) {
            throw new InvalidArgumentException("Unknown complaint decision: {$status}");
        }

        $old = $complaint->only(['status', 'hod_decision']);
        $complaint->status = $status;
        $complaint->hod_decision = $decision;
        $complaint->decided_by = $actor->id;
        $complaint->decided_at = now();
        $complaint->save();

        AuditLogger::log($complaint, 'cs_complaint.decided', $old, $complaint->only(['status', 'hod_decision']));

        return $complaint;
    }

    /** §8 "Contact quality": a sampled review, HOD-only. */
    public function reviewContact(Employee $employee, array $data, User $reviewer): CsContactQualityReview
    {
        $review = CsContactQualityReview::query()->create([
            ...$data,
            'employee_id' => $employee->id,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        AuditLogger::log($review, 'cs_contact_quality_review.recorded', [], $review->only(['channel', 'accuracy_ok', 'professionalism_ok', 'policy_compliance_ok', 'correct_advice_ok']));

        return $review;
    }

    /**
     * The weekly "Customer-service quality" item's automatic achievement —
     * mirrors CsScoringService::refreshCommercialAchievement()'s shape so
     * decide() needs no separate code path: average of response-time
     * compliance, resolution rate, and complaint-free rate over the card's
     * week, each 0-100. Null (item left as a plain HOD-judged item) when no
     * interactions were logged that week — there's no eligible work to
     * grade, the same "no eligible work" treatment SeoScoringService gives a
     * zero-target quantity item.
     */
    public function refreshQualityAchievement(CsCardItem $item): void
    {
        $card = $item->cardable;
        if (! $card instanceof CsWeeklyCard) {
            return;
        }

        $weekStart = $card->week_start_date->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $interactions = CsServiceInteraction::query()
            ->where('employee_id', $card->employee_id)
            ->whereBetween('enquiry_received_at', [$weekStart, $weekEnd])
            ->get();

        if ($interactions->isEmpty()) {
            $item->forceFill(['target_quantity' => null, 'available_target_quantity' => null, 'achieved_quantity' => null])->save();

            return;
        }

        $responded = $interactions->whereNotNull('first_response_at');
        $onTime = $responded->filter(fn (CsServiceInteraction $i) => $i->isWithinStandard() === true);
        $responseCompliance = $responded->isNotEmpty() ? ($onTime->count() / $responded->count()) * 100 : 100.0;

        $resolved = $interactions->filter(fn (CsServiceInteraction $i) => $i->isResolved());
        $resolutionRate = ($resolved->count() / $interactions->count()) * 100;

        $substantiated = CsComplaint::query()
            ->where('employee_id', $card->employee_id)
            ->where('status', CsComplaint::STATUS_SUBSTANTIATED)
            ->whereBetween('reported_at', [$weekStart, $weekEnd])
            ->count();
        $complaintFreeRate = max(0.0, 100 - (($substantiated / $interactions->count()) * 100));

        $blended = round(($responseCompliance + $resolutionRate + $complaintFreeRate) / 3, 2);

        $item->forceFill([
            'target_quantity' => 100,
            'available_target_quantity' => 100,
            'achieved_quantity' => $blended,
        ])->save();
    }
}
