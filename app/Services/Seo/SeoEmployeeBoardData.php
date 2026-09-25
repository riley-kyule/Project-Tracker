<?php

namespace App\Services\Seo;

use App\Models\SeoDailyCard;
use App\Models\SeoWeeklyCard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * The "Score Based" tab's payload on the SEO Board (resources/js/pages/boards/show.tsx)
 * — today's daily card, this week's card, and personal history. Shared with
 * the /seo-board route, which now just redirects here rather than rendering
 * its own page, so the weighted scoring system lives on the one board page
 * the team already has bookmarked, per the "keep Kanban and Score Based on
 * one page, not two linked pages" request.
 */
class SeoEmployeeBoardData
{
    public function __construct(
        private readonly SeoCardLifecycleService $lifecycle,
        private readonly SeoPerformanceQuery $query,
    ) {}

    /** @return array<string, mixed>|null null when $user isn't a current SEO team member — the tab simply doesn't render. */
    public function forUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user->isSeoEmployee()) {
            return null;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return null;
        }

        // Validated by isSeoEmployee() above to be SEO itself or a descendant
        // of it — kept as the user's own specific department (not collapsed
        // to the top-level SEO id) so a future SEO sub-team still files under
        // itself, same as SeoHodPanelData::forDepartment's descendantIds() scan expects.
        $seoBoardDepartmentId = $user->department_id;

        $today = $this->lifecycle->businessDay();

        $dailyCard = SeoDailyCard::query()->where('employee_id', $employee->id)->whereDate('work_date', $today->toDateString())->first();
        if ($dailyCard === null) {
            $dailyCard = $this->lifecycle->createNextCard($employee, $seoBoardDepartmentId, $today->copy()->subDay());
        }
        $dailyCard->load(['items' => fn ($q) => $q->orderBy('position'), 'items.evidence']);

        $weekStart = $today->copy()->startOfWeek();
        $weeklyCard = SeoWeeklyCard::query()->where('employee_id', $employee->id)->whereDate('week_start_date', $weekStart->toDateString())->first();
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
                'items' => SeoPerformanceQuery::itemsPayload($dailyCard->items),
            ],
            'weeklyCard' => $weeklyCard ? [
                'id' => $weeklyCard->id,
                'week_start_date' => $weeklyCard->week_start_date->toDateString(),
                'week_end_date' => $weeklyCard->week_end_date->toDateString(),
                'status' => $weeklyCard->status,
                'planned_points' => $weeklyCard->planned_points,
                'approved_points' => $weeklyCard->approved_points !== null ? (float) $weeklyCard->approved_points : null,
                'can_update' => Gate::forUser($user)->allows('update', $weeklyCard),
                'items' => SeoPerformanceQuery::itemsPayload($weeklyCard->items),
            ] : null,
            'history' => $this->query->employeeSummary($employee, $range['from'], $range['to']),
            'range' => ['from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString(), 'period' => $range['period']],
        ];
    }

    /** True for any user who currently has a Score Based tab to see — used to decide whether the SEO Board's Kanban board even needs to resolve this payload at all. */
    public function appliesTo(User $user): bool
    {
        return $user->isSeoEmployee();
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
