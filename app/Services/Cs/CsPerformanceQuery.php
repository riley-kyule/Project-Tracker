<?php

namespace App\Services\Cs;

use App\Models\CompanySetting;
use App\Models\CsCardItem;
use App\Models\CsDailyCard;
use App\Models\CsFinalScore;
use App\Models\CsSalesRecord;
use App\Models\CsWeeklyCard;
use App\Models\CsWeeklyTarget;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The single source of truth behind the Customer Service Board dashboards
 * (§12), the employee productivity history, the HOD productivity section,
 * and the MCP tool (§13) — so all four always agree. Mirrors
 * App\Services\Seo\SeoPerformanceQuery.
 */
class CsPerformanceQuery
{
    /** @return array<string, mixed> */
    public function employeeSummary(Employee $employee, Carbon $from, Carbon $to): array
    {
        $cards = CsDailyCard::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->orderBy('work_date')
            ->get();

        $items = CsCardItem::query()
            ->where('cardable_type', CsDailyCard::class)
            ->whereIn('cardable_id', $cards->pluck('id'))
            ->get();

        $decided = $items->filter(fn (CsCardItem $i) => $i->isDecided());
        $onTime = $decided->where('hod_decision', CsCardItem::DECISION_APPROVED)->count();
        $late = $decided->where('hod_decision', CsCardItem::DECISION_APPROVED_LATE)->count();
        $corrections = $decided->whereIn('hod_decision', [CsCardItem::DECISION_MINOR_CORRECTION, CsCardItem::DECISION_MAJOR_REWORK])->count();
        $rejected = $decided->where('hod_decision', CsCardItem::DECISION_REJECTED)->count();
        $decidedCount = $decided->count();
        $blockedPoints = $items->where('employee_status', CsCardItem::STATUS_BLOCKED)->sum('weight');
        $missingEvidence = $items->filter(fn (CsCardItem $i) => $i->evidence_required && $i->employee_status === CsCardItem::STATUS_SUBMITTED && $i->evidence()->count() === 0)->count();

        $finalScores = CsFinalScore::query()
            ->where('employee_id', $employee->id)
            ->whereDate('week_start_date', '>=', $from->copy()->startOfWeek()->toDateString())
            ->whereDate('week_start_date', '<=', $to->copy()->endOfWeek()->toDateString())
            ->orderBy('week_start_date')
            ->get();

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'department' => $employee->department?->name,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'cards' => $cards->load(['items' => fn ($q) => $q->orderBy('position'), 'items.evidence'])->map(fn (CsDailyCard $c) => [
                'card_id' => $c->id,
                'work_date' => $c->work_date->toDateString(),
                'status' => $c->status,
                'planned_points' => $c->planned_points,
                'employee_submitted_points' => $c->employee_submitted_points !== null ? (float) $c->employee_submitted_points : null,
                'approved_points' => $c->approved_points !== null ? (float) $c->approved_points : null,
                'quota_percentage' => $c->approved_points !== null ? round(((float) $c->approved_points / max(1, $c->planned_points)) * 100, 1) : null,
                'items' => self::itemsPayload($c->items),
            ])->all(),
            'on_time_count' => $onTime,
            'late_count' => $late,
            'correction_count' => $corrections,
            'rejection_count' => $rejected,
            'decided_count' => $decidedCount,
            'on_time_rate' => self::rate($onTime, $decidedCount),
            'late_rate' => self::rate($late, $decidedCount),
            'correction_rate' => self::rate($corrections, $decidedCount),
            'rejection_rate' => self::rate($rejected, $decidedCount),
            'blocked_points' => (float) $blockedPoints,
            'missing_evidence_count' => $missingEvidence,
            'commercial' => $this->commercialWindow($employee, $from, $to),
            'weekly_final_scores' => $finalScores->map(fn (CsFinalScore $s) => [
                'week_start_date' => $s->week_start_date->toDateString(),
                'avg_daily_approved_score' => $s->avg_daily_approved_score !== null ? (float) $s->avg_daily_approved_score : null,
                'weekly_approved_score' => $s->weekly_approved_score !== null ? (float) $s->weekly_approved_score : null,
                'final_score' => $s->final_score !== null ? (float) $s->final_score : null,
                'is_final' => $s->is_final,
            ])->all(),
        ];
    }

    /** One week's commercial achievement for one employee — reused by the midnight report so the number the employee is scored on and the number in their inbox always agree. */
    public function commercialSummary(Employee $employee, Carbon $weekStart): array
    {
        $weekStart = $weekStart->copy()->startOfWeek();

        $target = CsWeeklyTarget::query()
            ->where('employee_id', $employee->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->first();

        $records = CsSalesRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->cleared()
            ->get();

        $newRecords = $records->where('category', CsSalesRecord::CATEGORY_NEW);
        $renewalRecords = $records->whereIn('category', [CsSalesRecord::CATEGORY_RENEWAL, CsSalesRecord::CATEGORY_REACTIVATION]);

        return [
            'week_start_date' => $weekStart->toDateString(),
            'new_customers' => $newRecords->pluck('customer_identifier')->unique()->count(),
            'new_customers_target' => $target?->new_customers_target ?? 0,
            'new_customer_revenue' => (float) $newRecords->sum(fn (CsSalesRecord $r) => $r->reporting_currency_amount ?? $r->amount),
            'new_customer_revenue_target' => $target !== null ? (float) $target->new_customer_revenue_target : 0.0,
            'renewed_customers' => $renewalRecords->pluck('customer_identifier')->unique()->count(),
            'renewed_customers_target' => $target?->renewed_customers_target ?? 0,
            'retained_revenue' => (float) $renewalRecords->sum(fn (CsSalesRecord $r) => $r->reporting_currency_amount ?? $r->amount),
            'retained_revenue_target' => $target !== null ? (float) $target->retained_revenue_target : 0.0,
            'currency' => $target?->currency ?? 'KES',
        ];
    }

    /** @return list<array<string, mixed>> one row per week the range covers */
    private function commercialWindow(Employee $employee, Carbon $from, Carbon $to): array
    {
        $weeks = [];
        $cursor = $from->copy()->startOfWeek();
        $end = $to->copy()->startOfWeek();

        while ($cursor->lte($end)) {
            $weeks[] = $this->commercialSummary($employee, $cursor);
            $cursor = $cursor->addWeek();
        }

        return $weeks;
    }

    /** "Today" — every Customer Service employee in the department, their live daily card, items included so the HOD hub can act on them inline. */
    public function departmentToday(Department $department): array
    {
        $today = CsDailyCard::query()
            ->whereIn('department_id', $department->descendantIds())
            ->whereDate('work_date', now($this->timezone())->toDateString())
            ->with(['employee', 'items' => fn ($q) => $q->orderBy('position'), 'items.evidence'])
            ->get();

        return $today->map(fn (CsDailyCard $c) => [
            'card_id' => $c->id,
            'employee_id' => $c->employee_id,
            'employee_name' => $c->employee?->full_name,
            'status' => $c->status,
            'planned_points' => $c->planned_points,
            'approved_points' => $c->approved_points !== null ? (float) $c->approved_points : null,
            'employee_submitted_points' => $c->employee_submitted_points !== null ? (float) $c->employee_submitted_points : null,
            'missing_evidence_count' => $c->items->filter(fn (CsCardItem $i) => $i->evidence_required && $i->employee_status === CsCardItem::STATUS_SUBMITTED && $i->evidence->count() === 0)->count(),
            'blocked_items' => $c->items->where('employee_status', CsCardItem::STATUS_BLOCKED)->count(),
            'items' => self::itemsPayload($c->items),
        ])->all();
    }

    /** Cards from before today that still have at least one undecided item — see SeoPerformanceQuery::departmentAwaitingReview for why this exists. */
    public function departmentAwaitingReview(Department $department, int $days = 14): array
    {
        $since = now($this->timezone())->subDays($days)->toDateString();
        $today = now($this->timezone())->toDateString();

        $cards = CsDailyCard::query()
            ->whereIn('department_id', $department->descendantIds())
            ->whereDate('work_date', '>=', $since)
            ->whereDate('work_date', '<', $today)
            ->whereHas('items', fn ($q) => $q->whereNull('hod_decision'))
            ->with(['employee', 'items' => fn ($q) => $q->orderBy('position'), 'items.evidence'])
            ->orderBy('work_date')
            ->get();

        return $cards->map(fn (CsDailyCard $c) => [
            'card_id' => $c->id,
            'employee_id' => $c->employee_id,
            'employee_name' => $c->employee?->full_name,
            'work_date' => $c->work_date->toDateString(),
            'status' => $c->status,
            'planned_points' => $c->planned_points,
            'approved_points' => $c->approved_points !== null ? (float) $c->approved_points : null,
            'pending_item_count' => $c->items->whereNull('hod_decision')->count(),
            'items' => self::itemsPayload($c->items),
        ])->all();
    }

    /** @param  Collection<int, CsCardItem>  $items */
    public static function itemsPayload($items): array
    {
        return $items->map(fn (CsCardItem $i) => [
            'id' => $i->id,
            'section' => $i->section,
            'name' => $i->name,
            'classification' => $i->classification,
            'metric_type' => $i->metric_type,
            'weight' => (float) $i->weight,
            'target_quantity' => $i->target_quantity !== null ? (float) $i->target_quantity : null,
            'achieved_quantity' => $i->achieved_quantity !== null ? (float) $i->achieved_quantity : null,
            'quantity_unit' => $i->quantity_unit,
            'due_time' => $i->due_time !== null ? substr($i->due_time, 0, 5) : null,
            'completion_criteria' => $i->completion_criteria,
            'evidence_required' => $i->evidence_required,
            'employee_status' => $i->employee_status,
            'employee_comment' => $i->employee_comment,
            'blocker_reason' => $i->blocker_reason,
            'hod_decision' => $i->hod_decision,
            'hod_decision_reason' => $i->hod_decision_reason,
            'completion_factor' => $i->completion_factor !== null ? (float) $i->completion_factor : null,
            'earned_points' => $i->earned_points !== null ? (float) $i->earned_points : null,
            'evidence' => $i->evidence->map(fn ($e) => ['id' => $e->id, 'original_name' => $e->original_name])->all(),
        ])->all();
    }

    /** Repeated incompletion, unexplained lateness, rejected work, missing evidence, recurring blockers, over the trailing 4 weeks. */
    public function exceptions(Department $department): array
    {
        $since = now($this->timezone())->subWeeks(4)->toDateString();

        $cardIds = CsDailyCard::query()
            ->whereIn('department_id', $department->descendantIds())
            ->where('work_date', '>=', $since)
            ->pluck('id');

        $items = CsCardItem::query()
            ->where('cardable_type', CsDailyCard::class)
            ->whereIn('cardable_id', $cardIds)
            ->with('cardable.employee')
            ->get();

        $byEmployee = $items->groupBy(fn (CsCardItem $i) => $i->cardable?->employee_id);

        return $byEmployee->map(function ($group, $employeeId) {
            $employee = $group->first()?->cardable?->employee;
            $total = $group->count();
            $incomplete = $group->where('employee_status', CsCardItem::STATUS_NOT_STARTED)->count();
            $late = $group->where('hod_decision', CsCardItem::DECISION_APPROVED_LATE)->count();
            $rejected = $group->where('hod_decision', CsCardItem::DECISION_REJECTED)->count();
            $missingEvidence = $group->filter(fn (CsCardItem $i) => $i->evidence_required && $i->employee_status === CsCardItem::STATUS_SUBMITTED && $i->evidence()->count() === 0)->count();
            $blocked = $group->where('employee_status', CsCardItem::STATUS_BLOCKED)->count();

            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee?->full_name,
                'total_count' => $total,
                'incomplete_count' => $incomplete,
                'late_count' => $late,
                'rejected_count' => $rejected,
                'missing_evidence_count' => $missingEvidence,
                'blocked_count' => $blocked,
                'late_rate' => self::rate($late, $total),
                'rejected_rate' => self::rate($rejected, $total),
            ];
        })->filter(fn (array $row) => $row['incomplete_count'] + $row['late_count'] + $row['rejected_count'] + $row['missing_evidence_count'] + $row['blocked_count'] > 0)
            ->values()->all();
    }

    /** Weekly plan/approval status for a department's employees, current week. */
    public function departmentWeekly(Department $department): array
    {
        $weekStart = now($this->timezone())->startOfWeek()->toDateString();

        return CsWeeklyCard::query()
            ->whereIn('department_id', $department->descendantIds())
            ->whereDate('week_start_date', $weekStart)
            ->with(['employee', 'items' => fn ($q) => $q->orderBy('position'), 'items.evidence'])
            ->get()
            ->map(fn (CsWeeklyCard $c) => [
                'card_id' => $c->id,
                'employee_id' => $c->employee_id,
                'employee_name' => $c->employee?->full_name,
                'status' => $c->status,
                'planned_points' => $c->planned_points,
                'approved_points' => $c->approved_points !== null ? (float) $c->approved_points : null,
                'items' => self::itemsPayload($c->items),
            ])->all();
    }

    /** 4-week and 12-week trends and employee comparison — one table: rows are employees, columns are weeks, cells are that week's final score. */
    public function teamPerformance(Department $department, int $weeks = 4): array
    {
        $tz = $this->timezone();
        $weekStarts = collect(range($weeks - 1, 0))
            ->map(fn (int $i) => now($tz)->startOfWeek()->subWeeks($i)->toDateString())
            ->values();

        $employees = CsAccess::scoredEmployees($department);

        $scores = CsFinalScore::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('week_start_date', '>=', $weekStarts->first())
            ->whereDate('week_start_date', '<=', $weekStarts->last())
            ->get()
            ->groupBy('employee_id');

        return [
            'weeks' => $weekStarts->all(),
            'employees' => $employees->map(function (Employee $employee) use ($scores, $weekStarts) {
                $byWeek = ($scores->get($employee->id) ?? collect())->keyBy(fn (CsFinalScore $s) => $s->week_start_date->toDateString());

                $finalScores = $weekStarts->map(fn (string $week) => $byWeek->get($week)?->final_score !== null ? (float) $byWeek->get($week)->final_score : null);

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'final_scores' => $finalScores->all(),
                    'average_final_score' => $finalScores->filter(fn (?float $v) => $v !== null)->avg(),
                ];
            })->all(),
        ];
    }

    private function timezone(): string
    {
        return CompanySetting::current()->timezone ?: 'Africa/Nairobi';
    }

    /** Percentage of $total, rounded to 1dp — null (not 0) when there's nothing to rate yet. */
    private static function rate(int $count, int $total): ?float
    {
        return $total > 0 ? round(($count / $total) * 100, 1) : null;
    }
}
