<?php

namespace App\Services\Cs;

use App\Models\CsCardItem;
use App\Models\CsDailyCard;
use App\Models\CsFinalScore;
use App\Models\CsSalesRecord;
use App\Models\CsTaskTemplate;
use App\Models\CsWeeklyCard;
use App\Models\CsWeeklyTarget;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Seo\WorkCalendarService;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The single choke point for every score-affecting write on the Customer
 * Service Board (Customer Service Board Requirements Specification v1.0
 * §7/§8/§10). Every weight, target, and HOD-decision mutation goes through
 * here so the "no self-scoring" rule and the audit trail are enforced
 * consistently rather than scattered across controllers. Mirrors
 * App\Services\Seo\SeoScoringService, plus refreshCommercialAchievement()
 * for the two commercial weekly items (§3) and a delegation to
 * CsServiceQualityService for the "Customer-service quality" weekly item
 * (§8) — all three compute their own achievement rather than taking a
 * manually typed figure.
 */
class CsScoringService
{
    public function __construct(
        private readonly WorkCalendarService $calendar,
        private readonly CsServiceQualityService $serviceQuality,
    ) {}

    /** §7 credit table. Null for the three "excluded from denominator" treatments — those carry no factor at all. */
    public function qualityFactor(string $decision): ?float
    {
        return match ($decision) {
            CsCardItem::DECISION_APPROVED => 100.0,
            CsCardItem::DECISION_APPROVED_LATE => 80.0,
            CsCardItem::DECISION_MINOR_CORRECTION => 75.0,
            CsCardItem::DECISION_MAJOR_REWORK => 50.0,
            CsCardItem::DECISION_REJECTED => 0.0,
            CsCardItem::DECISION_EXEMPTED, CsCardItem::DECISION_EXCLUDED, CsCardItem::DECISION_CARRIED_FORWARD => null,
            default => throw new InvalidArgumentException("Unknown Customer Service item decision: {$decision}"),
        };
    }

    /** §7: accepted / available target, capped at 100%. Null when the item isn't quantity-based. Also the formula the two commercial weekly items reuse (see refreshCommercialAchievement()). */
    public function quantityAchievement(?float $accepted, ?float $availableTarget): ?float
    {
        if ($availableTarget === null || $availableTarget <= 0) {
            return null;
        }

        return min(100.0, round((($accepted ?? 0) / $availableTarget) * 100, 2));
    }

    /**
     * §3 "Commercial achievement formula": 50% of the relevant customer-count
     * attainment plus 50% of the relevant revenue attainment, each capped at
     * 100% before blending. Reads cleared cs_sales_records for $employee's
     * $weekStart against their cs_weekly_targets row, and stores the result
     * onto $item as achieved_quantity/target_quantity/available_target_quantity
     * (fixed 0-100 scale) so the ordinary quantityAchievement() ratio — and
     * therefore CsCardItemController::decide() — needs no separate code path
     * for commercial items. A no-op when $item isn't a metric_type item or no
     * target has been set for the week yet.
     */
    public function refreshCommercialAchievement(CsCardItem $item): void
    {
        if ($item->metric_type === null) {
            return;
        }

        $card = $item->cardable;
        if (! $card instanceof CsWeeklyCard) {
            return;
        }

        $target = CsWeeklyTarget::query()
            ->where('employee_id', $card->employee_id)
            ->whereDate('week_start_date', $card->week_start_date->toDateString())
            ->first();

        if ($target === null) {
            return;
        }

        $categories = $item->metric_type === CsTaskTemplate::METRIC_NEW_SALES
            ? [CsSalesRecord::CATEGORY_NEW]
            : [CsSalesRecord::CATEGORY_RENEWAL, CsSalesRecord::CATEGORY_REACTIVATION];

        $records = CsSalesRecord::query()
            ->where('employee_id', $card->employee_id)
            ->whereDate('week_start_date', $card->week_start_date->toDateString())
            ->whereIn('category', $categories)
            ->cleared()
            ->get();

        $customerCount = $records->pluck('customer_identifier')->unique()->count();
        $revenue = (float) $records->sum(fn (CsSalesRecord $r) => $r->reporting_currency_amount ?? $r->amount);

        $countTarget = $item->metric_type === CsTaskTemplate::METRIC_NEW_SALES
            ? (int) $target->new_customers_target
            : (int) $target->renewed_customers_target;
        $revenueTarget = $item->metric_type === CsTaskTemplate::METRIC_NEW_SALES
            ? (float) $target->new_customer_revenue_target
            : (float) $target->retained_revenue_target;

        $countAttainment = $countTarget > 0 ? min(100.0, ($customerCount / $countTarget) * 100) : ($customerCount > 0 ? 100.0 : 0.0);
        $revenueAttainment = $revenueTarget > 0 ? min(100.0, ($revenue / $revenueTarget) * 100) : ($revenue > 0 ? 100.0 : 0.0);

        $blended = round(($countAttainment * 0.5) + ($revenueAttainment * 0.5), 2);

        $item->forceFill([
            'target_quantity' => 100,
            'available_target_quantity' => 100,
            'achieved_quantity' => $blended,
        ])->save();
    }

    /**
     * Record the HOD's decision on one item — the only place hod_decision,
     * completion_factor and earned_points are ever set. $reason is required
     * for every non-full-credit outcome so the "reason must be recorded"
     * rule can't be bypassed by a bare status flip.
     */
    public function decide(
        CsCardItem $item,
        string $decision,
        ?string $reason,
        User $actor,
        ?float $acceptedQuantity = null,
        ?float $availableTargetOverride = null,
    ): CsCardItem {
        if (! in_array($decision, CsCardItem::DECISIONS, true)) {
            throw new InvalidArgumentException("Unknown Customer Service item decision: {$decision}");
        }

        if ($decision !== CsCardItem::DECISION_APPROVED && ($reason === null || trim($reason) === '')) {
            throw new InvalidArgumentException('A reason is required for any decision other than full approval.');
        }

        if ($item->metric_type === CsTaskTemplate::METRIC_SERVICE_QUALITY) {
            $this->serviceQuality->refreshQualityAchievement($item);
            $item->refresh();
        } elseif ($item->isAutoCalculatedMetric()) {
            $this->refreshCommercialAchievement($item);
            $item->refresh();
        }

        $old = $item->only(['hod_decision', 'hod_decision_reason', 'completion_factor', 'earned_points', 'achieved_quantity', 'available_target_quantity']);

        if (! $item->isAutoCalculatedMetric() && $acceptedQuantity !== null) {
            $item->achieved_quantity = $acceptedQuantity;
        }
        if (! $item->isAutoCalculatedMetric() && $availableTargetOverride !== null) {
            $item->available_target_quantity = $availableTargetOverride;
        }

        $quality = $this->qualityFactor($decision);
        $requiresQuantity = $item->isAutoCalculatedMetric() || (bool) ($item->template?->requires_quantity ?? ($item->target_quantity !== null));
        $quantity = $requiresQuantity
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

        AuditLogger::log($item, 'cs_item.decided', $old, $item->only([
            'hod_decision', 'hod_decision_reason', 'completion_factor', 'earned_points', 'achieved_quantity', 'available_target_quantity',
        ]));

        $this->recalculateCard($item->cardable);

        return $item;
    }

    /** Any weight change after assignment requires a reason. */
    public function updateWeight(CsCardItem $item, float $weight, string $reason): CsCardItem
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to change an item weight after assignment.');
        }

        $old = $item->only(['weight']);
        $item->weight = $weight;
        if ($item->isDecided()) {
            $item->earned_points = $item->completion_factor !== null ? round(($weight * (float) $item->completion_factor) / 100, 2) : null;
        }
        $item->save();

        AuditLogger::log($item, 'cs_item.weight_changed', $old, ['weight' => $item->weight, 'reason' => $reason]);

        $this->recalculateCard($item->cardable);

        return $item;
    }

    public function updateTarget(CsCardItem $item, float $targetQuantity, string $reason): CsCardItem
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to change a target after assignment.');
        }

        $old = $item->only(['target_quantity']);
        $item->target_quantity = $targetQuantity;
        $item->save();

        AuditLogger::log($item, 'cs_item.target_changed', $old, ['target_quantity' => $item->target_quantity, 'reason' => $reason]);

        return $item;
    }

    /** §14 "Card totals": every daily/weekly card must total exactly 100 planned points. */
    public function totalWeight(CsDailyCard|CsWeeklyCard $card): float
    {
        return (float) $card->items()->sum('weight');
    }

    public function totalsExactly100(CsDailyCard|CsWeeklyCard $card): bool
    {
        return abs($this->totalWeight($card) - 100.0) < 0.01;
    }

    /**
     * Recomputes a card's live approved_points. Pending items keep their
     * weight in the denominator but contribute zero to the numerator, so the
     * score can never exceed what the HOD has actually approved — the "no
     * self-scoring" rule (§14). Excluded/exempted/carried-forward items drop
     * out of the denominator entirely, normalising the remaining points back
     * up to a 100-point scale.
     */
    public function recalculateCard(CsDailyCard|CsWeeklyCard $card): void
    {
        $items = $card->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        $counted = $items->reject(fn (CsCardItem $i) => in_array($i->hod_decision, CsCardItem::DECISIONS_EXCLUDED_FROM_DENOMINATOR, true));
        $denominator = (float) $counted->sum('weight');
        $anyDecided = $items->contains(fn (CsCardItem $i) => $i->isDecided());

        $approved = null;
        if ($anyDecided && $denominator > 0) {
            $numerator = $counted->sum(fn (CsCardItem $i) => $i->earned_points !== null ? (float) $i->earned_points : 0.0);
            $approved = round(($numerator / $denominator) * 100, 2);
        }

        $allDecided = $items->every(fn (CsCardItem $i) => $i->isDecided());

        $card->forceFill([
            'approved_points' => $approved,
            'approved_at' => $allDecided ? now() : $card->approved_at,
        ])->save();

        $employee = $card->employee;
        $weekStart = $card instanceof CsWeeklyCard
            ? $card->week_start_date
            : Carbon::parse($card->work_date)->startOfWeek();

        if ($employee !== null) {
            $this->recalculateFinalScore($employee, $weekStart);
        }
    }

    /** The employee's own self-reported figure for the midnight report's "provisional points" — informational only, never the approved score. */
    public function recalculateEmployeeSubmitted(CsDailyCard|CsWeeklyCard $card): void
    {
        $submitted = $card->items()->get()->sum(fn (CsCardItem $i) => $i->employee_status === CsCardItem::STATUS_SUBMITTED ? (float) $i->weight : 0.0);

        $card->forceFill(['employee_submitted_points' => round($submitted, 2)])->save();
    }

    /** §2 formula: (avg approved daily score x 70%) + (approved weekly score x 30%), over actual working days only. */
    public function recalculateFinalScore(Employee $employee, Carbon $weekStart): void
    {
        $weekStart = $weekStart->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $dailyCards = CsDailyCard::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $weekStart->toDateString())
            ->whereDate('work_date', '<=', $weekEnd->toDateString())
            ->get();

        $workingDays = $this->calendar->workingDaysBetween($employee, $weekStart, $weekEnd);

        $scored = $dailyCards->filter(fn (CsDailyCard $c) => $c->approved_points !== null);
        $avgDaily = $scored->isNotEmpty() ? round((float) $scored->avg('approved_points'), 2) : null;

        $weeklyCard = CsWeeklyCard::query()
            ->where('employee_id', $employee->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->first();

        $weeklyScore = $weeklyCard?->approved_points !== null ? (float) $weeklyCard->approved_points : null;

        $final = null;
        if ($avgDaily !== null || $weeklyScore !== null) {
            $final = round((($avgDaily ?? 0) * 0.7) + (($weeklyScore ?? 0) * 0.3), 2);
        }

        $isFinal = $workingDays > 0
            && $dailyCards->where('status', '!=', CsDailyCard::STATUS_OPEN)->count() >= $workingDays
            && $dailyCards->every(fn (CsDailyCard $c) => $c->approved_points !== null)
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

        // Not updateOrCreate(): see SeoScoringService::recalculateFinalScore
        // for why comparing the raw search string against week_start_date's
        // stored (cast-serialized) format would insert a duplicate row.
        $existing = CsFinalScore::query()
            ->where('employee_id', $employee->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->first();

        if ($existing !== null) {
            $existing->update($attributes);
        } else {
            CsFinalScore::query()->create([
                'employee_id' => $employee->id,
                'week_start_date' => $weekStart->toDateString(),
                ...$attributes,
            ]);
        }
    }
}
