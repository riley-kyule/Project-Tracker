<?php

namespace App\Services\Seo;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Models\Employee;
use App\Models\SeoTaskTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The SEO Board section embedded in the existing "My Department" dashboard
 * (DashboardController::department) rather than a standalone page — an HOD
 * already has one department home; this is data for a section on it, not a
 * second screen. Returns null when the viewed department has nothing to do
 * with the SEO Board, so the section simply doesn't render.
 */
class SeoHodPanelData
{
    public function __construct(private readonly SeoPerformanceQuery $query) {}

    /** @return array<string, mixed>|null */
    public function forDepartment(Department $viewedDepartment, User $user, ?int $historyEmployeeId, ?string $historyPeriod, int $teamWeeks = 4): ?array
    {
        $teamWeeks = in_array($teamWeeks, [4, 12], true) ? $teamWeeks : 4;

        if (! $user->can('seo.cards.approve') && ! $user->hasAnyRole(['CEO', 'Administrator'])) {
            return null;
        }

        $seoDepartment = $viewedDepartment->slug === 'seo'
            ? $viewedDepartment
            : Department::query()->whereIn('id', $viewedDepartment->descendantIds())->where('slug', 'seo')->first();

        if ($seoDepartment === null) {
            return null;
        }

        // Not Employee::department_id: that's the HR system-of-record field and
        // can legitimately point at a parent department (e.g. Marketing) for
        // org-chart reasons even when the person's actual platform login is
        // assigned to the SEO sub-department. User::department_id is the
        // authoritative "which team does this person work in day to day"
        // signal — the same one the sidebar and every other SEO Board access
        // check already relies on.
        $employees = Employee::query()->active()
            ->whereHas('user', fn ($q) => $q->where('department_id', $seoDepartment->id))
            ->orderBy('first_name')->get();

        $history = null;
        if ($historyEmployeeId !== null) {
            $employee = $employees->firstWhere('id', $historyEmployeeId);
            if ($employee !== null) {
                $range = $this->resolveRange($historyPeriod);
                $history = [
                    'summary' => $this->query->employeeSummary($employee, $range['from'], $range['to']),
                    'range' => ['from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString(), 'period' => $range['period']],
                ];
            }
        }

        return [
            'department' => $seoDepartment->only(['id', 'name']),
            'employees' => $employees->map(fn (Employee $e) => ['id' => $e->id, 'full_name' => $e->full_name])->values(),
            'today' => $this->query->departmentToday($seoDepartment),
            'awaitingReview' => $this->query->departmentAwaitingReview($seoDepartment),
            'weekly' => $this->query->departmentWeekly($seoDepartment),
            'exceptions' => $this->query->exceptions($seoDepartment),
            'teamPerformance' => $this->query->teamPerformance($seoDepartment, $teamWeeks),
            'teamWeeks' => $teamWeeks,
            'history' => $history,
            'productionTemplates' => SeoTaskTemplate::query()->active()->forCardType(SeoTaskTemplate::CARD_TYPE_DAILY)
                ->where('classification', SeoTaskTemplate::CLASSIFICATION_PRODUCTION)->orderBy('position')->get(),
            'weeklyTemplates' => SeoTaskTemplate::query()->active()->forCardType(SeoTaskTemplate::CARD_TYPE_WEEKLY)->orderBy('position')->get(),
            'notifications' => $user->can('seo.settings.manage') ? [
                'hod' => $seoDepartment->resolveHod(),
                'recipients' => DepartmentNotificationRecipient::query()->where('department_id', $seoDepartment->id)->orderByDesc('is_active')->orderBy('email')->get(),
            ] : null,
            'calibrationEndsAt' => CompanySetting::current()->seo_calibration_ends_at?->toDateString(),
        ];
    }

    /** @return array{from: Carbon, to: Carbon, period: string} */
    private function resolveRange(?string $period): array
    {
        $today = now();

        return match ($period) {
            'month' => ['from' => $today->copy()->startOfMonth(), 'to' => $today->copy()->endOfMonth(), 'period' => 'month'],
            'quarter' => ['from' => $today->copy()->startOfQuarter(), 'to' => $today->copy()->endOfQuarter(), 'period' => 'quarter'],
            default => ['from' => $today->copy()->startOfWeek(), 'to' => $today->copy()->endOfWeek(), 'period' => 'week'],
        };
    }
}
