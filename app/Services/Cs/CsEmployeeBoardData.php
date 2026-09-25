<?php

namespace App\Services\Cs;

use App\Models\CsDailyCard;
use App\Models\CsServiceInteraction;
use App\Models\CsWeeklyCard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * The "Score Based" tab's personal payload on the Customer Service
 * department's Kanban board (resources/js/pages/boards/show.tsx): today's
 * daily card, this week's card, commercial position, today's enquiries and
 * personal history. Returned only for a scored CS team member, and only ever
 * for that member's own records, so a member can never see another member's
 * cards. Mirrors App\Services\Seo\SeoEmployeeBoardData.
 */
class CsEmployeeBoardData
{
    public function __construct(
        private readonly CsCardLifecycleService $lifecycle,
        private readonly CsPerformanceQuery $query,
    ) {}

    /** True for any user who currently has a personal Score Based tab. */
    public function appliesTo(User $user): bool
    {
        return $user->isCsEmployee();
    }

    /** @return array<string, mixed>|null null when $user isn't a scored CS team member with an employee record. */
    public function forUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user->isCsEmployee()) {
            return null;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return null;
        }

        $today = $this->lifecycle->businessDay();

        $dailyCard = CsDailyCard::query()->where('employee_id', $employee->id)->whereDate('work_date', $today->toDateString())->first();
        if ($dailyCard === null) {
            $dailyCard = $this->lifecycle->createNextCard($employee, $user->department_id, $today->copy()->subDay());
        }
        $dailyCard->load(['items' => fn ($q) => $q->orderBy('position'), 'items.evidence']);

        $weekStart = $today->copy()->startOfWeek();
        $weeklyCard = CsWeeklyCard::query()->where('employee_id', $employee->id)->whereDate('week_start_date', $weekStart->toDateString())->first();
        $weeklyCard?->load(['items' => fn ($q) => $q->orderBy('position'), 'items.evidence']);

        $range = $this->resolveRange($request);

        return [
            'dailyCard' => [
                'id' => $dailyCard->id,
                'work_date' => $dailyCard->work_date->toDateString(),
                'status' => $dailyCard->status,
                'planned_points' => $dailyCard->planned_points,
                'employee_submitted_points' => $dailyCard->employee_submitted_points !== null ? (float) $dailyCard->employee_submitted_points : null,
                'approved_points' => $dailyCard->approved_points !== null ? (float) $dailyCard->approved_points : null,
                'can_update' => Gate::forUser($user)->allows('update', $dailyCard),
                'items' => CsPerformanceQuery::itemsPayload($dailyCard->items),
            ],
            'weeklyCard' => $weeklyCard ? [
                'id' => $weeklyCard->id,
                'week_start_date' => $weeklyCard->week_start_date->toDateString(),
                'week_end_date' => $weeklyCard->week_end_date->toDateString(),
                'status' => $weeklyCard->status,
                'planned_points' => $weeklyCard->planned_points,
                'approved_points' => $weeklyCard->approved_points !== null ? (float) $weeklyCard->approved_points : null,
                'can_update' => Gate::forUser($user)->allows('update', $weeklyCard),
                'items' => CsPerformanceQuery::itemsPayload($weeklyCard->items),
            ] : null,
            'commercial' => $this->query->commercialSummary($employee, $weekStart->copy()),
            'employeeId' => $employee->id,
            'interactions' => CsServiceInteraction::query()
                ->where('employee_id', $employee->id)
                ->whereDate('enquiry_received_at', $today->toDateString())
                ->orderByDesc('enquiry_received_at')
                ->get()
                ->map(fn (CsServiceInteraction $i) => [
                    'id' => $i->id,
                    'customer_identifier' => $i->customer_identifier,
                    'channel' => $i->channel,
                    'enquiry_received_at' => $i->enquiry_received_at->toDateTimeString(),
                    'first_response_at' => $i->first_response_at?->toDateTimeString(),
                    'response_standard_minutes' => $i->response_standard_minutes,
                    'resolution_status' => $i->resolution_status,
                ])->all(),
            'history' => $this->query->employeeSummary($employee, $range['from'], $range['to']),
            'range' => ['from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString(), 'period' => $range['period']],
        ];
    }

    /** @return array{from: Carbon, to: Carbon, period: string} */
    private function resolveRange(Request $request): array
    {
        $period = $request->string('period')->toString() ?: 'week';
        $today = now();

        return match ($period) {
            'month' => ['from' => $today->copy()->startOfMonth(), 'to' => $today->copy()->endOfMonth(), 'period' => 'month'],
            'quarter' => ['from' => $today->copy()->startOfQuarter(), 'to' => $today->copy()->endOfQuarter(), 'period' => 'quarter'],
            default => ['from' => $today->copy()->startOfWeek(), 'to' => $today->copy()->endOfWeek(), 'period' => 'week'],
        };
    }
}
