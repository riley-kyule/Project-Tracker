<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoDailyCard;
use App\Models\SeoWeeklyCard;
use App\Services\Seo\SeoCardLifecycleService;
use App\Services\Seo\SeoPerformanceQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My SEO Board" — the single employee-facing hub (today's daily card, this
 * week's card, and personal history as tabs on one page) replacing three
 * separate pages, per the simplification request. SEO Board Requirements
 * Specification v1.1 §4/§5/§11.1.
 */
class SeoBoardController extends Controller
{
    public function mine(Request $request, SeoCardLifecycleService $lifecycle, SeoPerformanceQuery $query): Response
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 404, 'No employee record is linked to your account.');

        // User::department_id, not Employee::department_id — see the identical
        // note in SeoHodPanelData::forDepartment(). A card filed under the
        // employee's HR department instead of their actual platform team would
        // silently vanish from that team's HOD view.
        $seoBoardDepartmentId = $request->user()->department_id ?? $employee->department_id;

        $today = $lifecycle->businessDay();

        $dailyCard = SeoDailyCard::query()->where('employee_id', $employee->id)->whereDate('work_date', $today->toDateString())->first();
        if ($dailyCard === null && $seoBoardDepartmentId !== null) {
            $dailyCard = $lifecycle->createNextCard($employee, $seoBoardDepartmentId, $today->copy()->subDay());
        }
        $dailyCard?->load(['items' => fn ($q) => $q->orderBy('position'), 'items.evidence']);

        $weekStart = $today->copy()->startOfWeek();
        $weeklyCard = SeoWeeklyCard::query()->where('employee_id', $employee->id)->whereDate('week_start_date', $weekStart->toDateString())->first();
        $weeklyCard?->load(['items' => fn ($q) => $q->orderBy('position'), 'items.evidence']);

        $range = $this->resolveRange($request);

        return Inertia::render('seo-board/index', [
            'dailyCard' => $dailyCard ? [
                'id' => $dailyCard->id,
                'work_date' => $dailyCard->work_date->toDateString(),
                'status' => $dailyCard->status,
                'planned_points' => $dailyCard->planned_points,
                'employee_submitted_points' => $dailyCard->employee_submitted_points !== null ? (float) $dailyCard->employee_submitted_points : null,
                'approved_points' => $dailyCard->approved_points !== null ? (float) $dailyCard->approved_points : null,
                'can_update' => Gate::forUser($request->user())->allows('update', $dailyCard),
                'items' => SeoPerformanceQuery::itemsPayload($dailyCard->items),
            ] : null,
            'weeklyCard' => $weeklyCard ? [
                'id' => $weeklyCard->id,
                'week_start_date' => $weeklyCard->week_start_date->toDateString(),
                'week_end_date' => $weeklyCard->week_end_date->toDateString(),
                'status' => $weeklyCard->status,
                'planned_points' => $weeklyCard->planned_points,
                'approved_points' => $weeklyCard->approved_points !== null ? (float) $weeklyCard->approved_points : null,
                'can_update' => Gate::forUser($request->user())->allows('update', $weeklyCard),
                'items' => SeoPerformanceQuery::itemsPayload($weeklyCard->items),
            ] : null,
            'history' => $query->employeeSummary($employee, $range['from'], $range['to']),
            'range' => ['from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString(), 'period' => $range['period']],
        ]);
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
