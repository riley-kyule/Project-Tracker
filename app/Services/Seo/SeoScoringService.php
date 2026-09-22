<?php

namespace App\Services\Seo;

use App\Models\Employee;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\SeoFinalScore;
use App\Models\SeoWeeklyCard;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The single choke point for every score-affecting write on the SEO Board
 * (SEO Board Requirements Specification v1.1 §6/§7/§9). Every weight,
 * target, and HOD-decision mutation goes through here so the "no
 * self-scoring" rule and the audit trail (§8/§9 rule 7) are enforced
 * consistently rather than scattered across controllers.
 */
class SeoScoringService
{
    public function __construct(private readonly WorkCalendarService $calendar) {}

    /** §6 credit table. Null for the three "excluded from denominator" treatments — those carry no factor at all. */
    public function qualityFactor(string $decision): ?float
    {
        return match ($decision) {
            SeoCardItem::DECISION_APPROVED => 100.0,
            SeoCardItem::DECISION_APPROVED_LATE => 80.0,
            SeoCardItem::DECISION_MINOR_CORRECTION => 75.0,
            SeoCardItem::DECISION_MAJOR_REWORK => 50.0,
            SeoCardItem::DECISION_REJECTED => 0.0,
            SeoCardItem::DECISION_EXEMPTED, SeoCardItem::DECISION_EXCLUDED, SeoCardItem::DECISION_CARRIED_FORWARD => null,
            default => throw new InvalidArgumentException("Unknown SEO item decision: {$decision}"),
        };
    }

    /** §7: accepted / available target, capped at 100%. Null when the item isn't quantity-based. */
    public function quantityAchievement(?float $accepted, ?float $availableTarget): ?float
    {
        if ($availableTarget === null || $availableTarget <= 0) {
            return null;
        }

        return min(100.0, round((($accepted ?? 0) / $availableTarget) * 100, 2));
    }

    /**
     * Record the HOD's decision on one item — the only place hod_decision,
     * completion_factor and earned_points are ever set. $reason is required
     * for every non-full-credit outcome so the §10/§6 "reason must be
     * recorded" rule can't be bypassed by a bare status flip.
     */
    public function decide(
        SeoCardItem $item,
        string $decision,
        ?string $reason,
        User $actor,
        ?float $acceptedQuantity = null,
        ?float $availableTargetOverride = null,
    ): SeoCardItem {
        if (! in_array($decision, SeoCardItem::DECISIONS, true)) {
            throw new InvalidArgumentException("Unknown SEO item decision: {$decision}");
        }

        if ($decision !== SeoCardItem::DECISION_APPROVED && ($reason === null || trim($reason) === '')) {
            throw new InvalidArgumentException('A reason is required for any decision other than full approval.');
        }

        $old = $item->only(['hod_decision', 'hod_decision_reason', 'completion_factor', 'earned_points', 'achieved_quantity', 'available_target_quantity']);

        if ($acceptedQuantity !== null) {
            $item->achieved_quantity = $acceptedQuantity;
        }
        if ($availableTargetOverride !== null) {
            $item->available_target_quantity = $availableTargetOverride;
        }

        $quality = $this->qualityFactor($decision);
        $quantity = $item->requires_quantity
            ? $this->quantityAchievement((float) ($item->achieved_quantity ?? 0), $item->available_target_quantity !== null ? (float) $item->available_target_quantity : ($item->target_quantity !== null ? (float) $item->target_quantity : null))
            : null;

        $factor = match (true) {
            $quality === null => null, // exempted/excluded/carried_forward
            $quantity !== null => round(($quality * $quantity) / 100, 2),
            default => $quality,
        };

        $item->hod_decision = $decision;
        $item->hod_decision_reason = $reason;
        $item->completion_factor = $factor;
        $item->earned_points = $factor !== null ? round(((float) $item->weight * $factor) / 100, 2) : null;
        $item->decided_by = $actor->id;
        $item->decided_at = now();
        $item->save();

        AuditLogger::log($item, 'seo_item.decided', $old, $item->only([
            'hod_decision', 'hod_decision_reason', 'completion_factor', 'earned_points', 'achieved_quantity', 'available_target_quantity',
        ]));

        $this->recalculateCard($item->cardable);

        return $item;
    }

    /** Any weight change after assignment requires a reason — SEO Board Spec §9 workflow rule 7. */
    public function updateWeight(SeoCardItem $item, float $weight, string $reason): SeoCardItem
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to change an item weight after assignment.');
        }

        $old = $item->only(['weight']);
        $item->weight = $weight;
        // A weight change invalidates any already-computed earned points until the HOD re-decides.
        if ($item->isDecided()) {
            $item->earned_points = $item->completion_factor !== null ? round(($weight * (float) $item->completion_factor) / 100, 2) : null;
        }
        $item->save();

        AuditLogger::log($item, 'seo_item.weight_changed', $old, ['weight' => $item->weight, 'reason' => $reason]);

        $this->recalculateCard($item->cardable);

        return $item;
    }

    public function updateTarget(SeoCardItem $item, float $targetQuantity, string $reason): SeoCardItem
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to change a target after assignment.');
        }

        $old = $item->only(['target_quantity']);
        $item->target_quantity = $targetQuantity;
        $item->save();

        AuditLogger::log($item, 'seo_item.target_changed', $old, ['target_quantity' => $item->target_quantity, 'reason' => $reason]);

        return $item;
    }

    /** SEO Board Spec §13 "Card totals": every daily/weekly card must total exactly 100 planned points. */
    public function totalWeight(SeoDailyCard|SeoWeeklyCard $card): float
    {
        return (float) $card->items()->sum('weight');
    }

    public function totalsExactly100(SeoDailyCard|SeoWeeklyCard $card): bool
    {
        return abs($this->totalWeight($card) - 100.0) < 0.01;
    }

    /**
     * Recomputes a card's live approved_points. Pending items keep their
     * weight in the denominator but contribute zero to the numerator, so the
     * score can never exceed what the HOD has actually approved — the "no
     * self-scoring" rule (§13). Excluded/exempted/carried-forward items drop
     * out of the denominator entirely, normalising the remaining points back
     * up to a 100-point scale, per §10.
     */
    public function recalculateCard(SeoDailyCard|SeoWeeklyCard $card): void
    {
        $items = $card->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        $counted = $items->reject(fn (SeoCardItem $i) => in_array($i->hod_decision, SeoCardItem::DECISIONS_EXCLUDED_FROM_DENOMINATOR, true));
        $denominator = (float) $counted->sum('weight');
        $anyDecided = $items->contains(fn (SeoCardItem $i) => $i->isDecided());

        $approved = null;
        if ($anyDecided && $denominator > 0) {
            $numerator = $counted->sum(fn (SeoCardItem $i) => $i->earned_points !== null ? (float) $i->earned_points : 0.0);
            $approved = round(($numerator / $denominator) * 100, 2);
        }

        $allDecided = $items->every(fn (SeoCardItem $i) => $i->isDecided());

        $card->forceFill([
            'approved_points' => $approved,
            'approved_at' => $allDecided ? now() : $card->approved_at,
        ])->save();

        $employee = $card->employee;
        $weekStart = $card instanceof SeoWeeklyCard
            ? $card->week_start_date
            : Carbon::parse($card->work_date)->startOfWeek();

        if ($employee !== null) {
            $this->recalculateFinalScore($employee, $weekStart);
        }
    }

    /** The employee's own self-reported figure for the midnight report's "provisional points" — informational only, never the approved score. */
    public function recalculateEmployeeSubmitted(SeoDailyCard|SeoWeeklyCard $card): void
    {
        $submitted = $card->items()->get()->sum(fn (SeoCardItem $i) => $i->employee_status === SeoCardItem::STATUS_SUBMITTED ? (float) $i->weight : 0.0);

        $card->forceFill(['employee_submitted_points' => round($submitted, 2)])->save();
    }

    /** §2 formula: (avg approved daily score x 70%) + (approved weekly score x 30%), over actual working days only. */
    public function recalculateFinalScore(Employee $employee, Carbon $weekStart): void
    {
        $weekStart = $weekStart->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        // whereBetween() (or a plain <=) against work_date's actual stored
        // full-datetime string would exclude the range's own end date — see
        // SeoCardLifecycleService::createNextCard for why equality/upper-bound
        // comparisons on this column must go through whereDate().
        $dailyCards = SeoDailyCard::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $weekStart->toDateString())
            ->whereDate('work_date', '<=', $weekEnd->toDateString())
            ->get();

        $workingDays = $this->calendar->workingDaysBetween($employee, $weekStart, $weekEnd);

        $scored = $dailyCards->filter(fn (SeoDailyCard $c) => $c->approved_points !== null);
        $avgDaily = $scored->isNotEmpty() ? round((float) $scored->avg('approved_points'), 2) : null;

        $weeklyCard = SeoWeeklyCard::query()
            ->where('employee_id', $employee->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->first();

        $weeklyScore = $weeklyCard?->approved_points !== null ? (float) $weeklyCard->approved_points : null;

        $final = null;
        if ($avgDaily !== null || $weeklyScore !== null) {
            $final = round((($avgDaily ?? 0) * 0.7) + (($weeklyScore ?? 0) * 0.3), 2);
        }

        $isFinal = $workingDays > 0
            && $dailyCards->where('status', '!=', SeoDailyCard::STATUS_OPEN)->count() >= $workingDays
            && $dailyCards->every(fn (SeoDailyCard $c) => $c->approved_points !== null)
            && ($weeklyCard === null || $weeklyCard->approved_points !== null);

        $attributes = [
            'week_end_date' => $weekEnd->toDateString(),
            'avg_daily_approved_score' => $avgDaily,
            'working_days_counted' => $scored->count(),
            'weekly_approved_score' => $weeklyScore,
            'final_score' => $final,
            'is_final' => $isFinal,
            'calculated_at' => now(),
        ];

        // Not updateOrCreate(): its lookup compares the raw search string against
        // week_start_date's stored (cast-serialized, full-datetime) format and never
        // matches, which would insert a duplicate row and trip the unique index on
        // the second recalculation of the same week. whereDate() compares correctly
        // regardless of how the column was originally serialized.
        $existing = SeoFinalScore::query()
            ->where('employee_id', $employee->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->first();

        if ($existing !== null) {
            $existing->update($attributes);
        } else {
            SeoFinalScore::query()->create([
                'employee_id' => $employee->id,
                'week_start_date' => $weekStart->toDateString(),
                ...$attributes,
            ]);
        }
    }
}
