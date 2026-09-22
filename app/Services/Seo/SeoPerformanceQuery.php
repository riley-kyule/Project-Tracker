<?php

namespace App\Services\Seo;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\SeoFinalScore;
use App\Models\SeoWeeklyCard;
use Illuminate\Support\Carbon;

/**
 * The single source of truth behind the SEO Board dashboards (§11), the
 * employee productivity history (§11.1), the HOD productivity section
 * (§11.2), and the MCP tool (§12) — so all four always agree.
 */
class SeoPerformanceQuery
{
    /** @return array<string, mixed> */
    public function employeeSummary(Employee $employee, Carbon $from, Carbon $to): array
    {
        // whereDate() range, not whereBetween()/where() — work_date is stored as a
        // full-datetime string, which a plain upper-bound or equality comparison
        // against a bare Y-m-d string would silently exclude. See
        // SeoCardLifecycleService::createNextCard for the full explanation.
        $cards = SeoDailyCard::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->orderBy('work_date')
            ->get();

        $items = SeoCardItem::query()
            ->where('cardable_type', SeoDailyCard::class)
            ->whereIn('cardable_id', $cards->pluck('id'))
            ->get();

        $decided = $items->filter(fn (SeoCardItem $i) => $i->isDecided());
        $onTime = $decided->where('hod_decision', SeoCardItem::DECISION_APPROVED)->count();
        $late = $decided->where('hod_decision', SeoCardItem::DECISION_APPROVED_LATE)->count();
        $corrections = $decided->whereIn('hod_decision', [SeoCardItem::DECISION_MINOR_CORRECTION, SeoCardItem::DECISION_MAJOR_REWORK])->count();
        $rejected = $decided->where('hod_decision', SeoCardItem::DECISION_REJECTED)->count();
        $decidedCount = $decided->count();
        $blockedPoints = $items->where('employee_status', SeoCardItem::STATUS_BLOCKED)->sum('weight');
        $missingEvidence = $items->filter(fn (SeoCardItem $i) => $i->evidence_required && $i->employee_status === SeoCardItem::STATUS_SUBMITTED && $i->evidence()->count() === 0)->count();

        $finalScores = SeoFinalScore::query()
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
            // items included per card so a history row can be expanded in place
            // to see (and, for an HOD, still decide) exactly what happened that
            // day — §11.1 "links from summary figures to the archived card".
            'cards' => $cards->load(['items' => fn ($q) => $q->orderBy('position'), 'items.evidence'])->map(fn (SeoDailyCard $c) => [
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
            // §11 "rates" — a raw count means nothing on its own (3 late out of
            // 4 decided items reads very differently from 3 out of 40), so the
            // dashboard needs the share of decided items, not just the tally.
            'decided_count' => $decidedCount,
            'on_time_rate' => self::rate($onTime, $decidedCount),
            'late_rate' => self::rate($late, $decidedCount),
            'correction_rate' => self::rate($corrections, $decidedCount),
            'rejection_rate' => self::rate($rejected, $decidedCount),
            'blocked_points' => (float) $blockedPoints,
            'missing_evidence_count' => $missingEvidence,
            'weekly_final_scores' => $finalScores->map(fn (SeoFinalScore $s) => [
                'week_start_date' => $s->week_start_date->toDateString(),
                'avg_daily_approved_score' => $s->avg_daily_approved_score !== null ? (float) $s->avg_daily_approved_score : null,
                'weekly_approved_score' => $s->weekly_approved_score !== null ? (float) $s->weekly_approved_score : null,
                'final_score' => $s->final_score !== null ? (float) $s->final_score : null,
                'is_final' => $s->is_final,
            ])->all(),
        ];
    }

    /** §11.2 "Today" — every SEO employee in the department, their live daily card, items included so the HOD hub can act on them inline without a separate page. */
    public function departmentToday(Department $department): array
    {
        $today = SeoDailyCard::query()
            ->whereIn('department_id', $department->descendantIds())
            ->whereDate('work_date', now($this->timezone())->toDateString())
            ->with(['employee', 'items' => fn ($q) => $q->orderBy('position'), 'items.evidence'])
            ->get();

        return $today->map(fn (SeoDailyCard $c) => [
            'card_id' => $c->id,
            'employee_id' => $c->employee_id,
            'employee_name' => $c->employee?->full_name,
            'status' => $c->status,
            'planned_points' => $c->planned_points,
            'approved_points' => $c->approved_points !== null ? (float) $c->approved_points : null,
            'employee_submitted_points' => $c->employee_submitted_points !== null ? (float) $c->employee_submitted_points : null,
            'missing_evidence_count' => $c->items->filter(fn (SeoCardItem $i) => $i->evidence_required && $i->employee_status === SeoCardItem::STATUS_SUBMITTED && $i->evidence->count() === 0)->count(),
            'overdue_items' => $c->items->whereNotNull('due_time')->whereIn('employee_status', [SeoCardItem::STATUS_NOT_STARTED, SeoCardItem::STATUS_IN_PROGRESS])->count(),
            'blocked_items' => $c->items->where('employee_status', SeoCardItem::STATUS_BLOCKED)->count(),
            'items' => self::itemsPayload($c->items),
        ])->all();
    }

    /**
     * §11.2 "Daily close" — cards from before today that still have at least
     * one undecided item. Once a day rolls over it drops out of
     * departmentToday() entirely; without this, an item left pending at
     * midnight would become permanently unreachable in the UI even though
     * the record itself is never lost. Bounded to a trailing window so a
     * long-neglected backlog doesn't silently grow unbounded.
     */
    public function departmentAwaitingReview(Department $department, int $days = 14): array
    {
        $since = now($this->timezone())->subDays($days)->toDateString();
        $today = now($this->timezone())->toDateString();

        $cards = SeoDailyCard::query()
            ->whereIn('department_id', $department->descendantIds())
            ->whereDate('work_date', '>=', $since)
            ->whereDate('work_date', '<', $today)
            ->whereHas('items', fn ($q) => $q->whereNull('hod_decision'))
            ->with(['employee', 'items' => fn ($q) => $q->orderBy('position'), 'items.evidence'])
            ->orderBy('work_date')
            ->get();

        return $cards->map(fn (SeoDailyCard $c) => [
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

    /** @param  \Illuminate\Support\Collection<int, SeoCardItem>  $items */
    public static function itemsPayload($items): array
    {
        return $items->map(fn (SeoCardItem $i) => [
            'id' => $i->id,
            'section' => $i->section,
            'name' => $i->name,
            'classification' => $i->classification,
            'weight' => (float) $i->weight,
            'target_quantity' => $i->target_quantity !== null ? (float) $i->target_quantity : null,
            'achieved_quantity' => $i->achieved_quantity !== null ? (float) $i->achieved_quantity : null,
            'quantity_unit' => $i->quantity_unit,
            'assigned_url' => $i->assigned_url,
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

    /** §11.2 "Exceptions" — repeated incompletion, unexplained lateness, rejected work, missing evidence, recurring blockers, over the trailing 4 weeks. */
    public function exceptions(Department $department): array
    {
        $since = now($this->timezone())->subWeeks(4)->toDateString();

        $cardIds = SeoDailyCard::query()
            ->whereIn('department_id', $department->descendantIds())
            ->where('work_date', '>=', $since)
            ->pluck('id');

        $items = SeoCardItem::query()
            ->where('cardable_type', SeoDailyCard::class)
            ->whereIn('cardable_id', $cardIds)
            ->with('cardable.employee')
            ->get();

        $byEmployee = $items->groupBy(fn (SeoCardItem $i) => $i->cardable?->employee_id);

        return $byEmployee->map(function ($group, $employeeId) {
            $employee = $group->first()?->cardable?->employee;
            $total = $group->count();
            $incomplete = $group->where('employee_status', SeoCardItem::STATUS_NOT_STARTED)->count();
            $late = $group->where('hod_decision', SeoCardItem::DECISION_APPROVED_LATE)->count();
            $rejected = $group->where('hod_decision', SeoCardItem::DECISION_REJECTED)->count();
            $missingEvidence = $group->filter(fn (SeoCardItem $i) => $i->evidence_required && $i->employee_status === SeoCardItem::STATUS_SUBMITTED && $i->evidence()->count() === 0)->count();
            $blocked = $group->where('employee_status', SeoCardItem::STATUS_BLOCKED)->count();

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

    /** §5.1/§9 weekly plan/approval status for a department's employees, current week — items included for the same reason as departmentToday(). */
    public function departmentWeekly(Department $department): array
    {
        $weekStart = now($this->timezone())->startOfWeek()->toDateString();

        return SeoWeeklyCard::query()
            ->whereIn('department_id', $department->descendantIds())
            ->whereDate('week_start_date', $weekStart)
            ->with(['employee', 'items' => fn ($q) => $q->orderBy('position'), 'items.evidence'])
            ->get()
            ->map(fn (SeoWeeklyCard $c) => [
                'card_id' => $c->id,
                'employee_id' => $c->employee_id,
                'employee_name' => $c->employee?->full_name,
                'status' => $c->status,
                'planned_points' => $c->planned_points,
                'approved_points' => $c->approved_points !== null ? (float) $c->approved_points : null,
                'items' => self::itemsPayload($c->items),
            ])->all();
    }

    /**
     * §11/§11.1 "4-week and 12-week trends" and "employee comparison within
     * the same SEO role" — one table, since a comparison across employees
     * over several weeks *is* the trend view: rows are employees, columns
     * are weeks, cells are that week's final score, so both requirements are
     * read off the same data without a second query shape.
     */
    public function teamPerformance(Department $department, int $weeks = 4): array
    {
        $tz = $this->timezone();
        $weekStarts = collect(range($weeks - 1, 0))
            ->map(fn (int $i) => now($tz)->startOfWeek()->subWeeks($i)->toDateString())
            ->values();

        $employees = Employee::query()->active()
            ->whereHas('user', fn ($q) => $q->where('department_id', $department->id))
            ->orderBy('first_name')
            ->get();

        $scores = SeoFinalScore::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('week_start_date', '>=', $weekStarts->first())
            ->whereDate('week_start_date', '<=', $weekStarts->last())
            ->get()
            ->groupBy('employee_id');

        return [
            'weeks' => $weekStarts->all(),
            'employees' => $employees->map(function (Employee $employee) use ($scores, $weekStarts) {
                $byWeek = ($scores->get($employee->id) ?? collect())->keyBy(fn (SeoFinalScore $s) => $s->week_start_date->toDateString());

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

    /** Percentage of $total, rounded to 1dp — null (not 0) when there's nothing to rate yet, so the UI can show "—" instead of a misleading 0%. */
    private static function rate(int $count, int $total): ?float
    {
        return $total > 0 ? round(($count / $total) * 100, 1) : null;
    }
}
