<?php

namespace App\Services\Cs;

use App\Models\CompanySetting;
use App\Models\CsComplaint;
use App\Models\CsPlatformAssignment;
use App\Models\CsSalesRecord;
use App\Models\CsTaskTemplate;
use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The Customer Service Board section embedded in the existing "My
 * Department" dashboard (DashboardController::department) rather than a
 * standalone page — an HOD already has one department home. Returns null
 * when the viewed department has nothing to do with the Customer Service
 * Board. Mirrors App\Services\Seo\SeoHodPanelData.
 */
class CsHodPanelData
{
    public function __construct(private readonly CsPerformanceQuery $query) {}

    /**
     * The (fully loaded) Customer Service department when $viewed is it or an
     * ancestor of it, else null. Always a fresh full model: callers such as
     * the board page pass a partial `department:id,name,slug` select, which
     * carries no manager_id and would make the leadership check always fail.
     */
    public function resolveCsDepartment(Department $viewed): ?Department
    {
        $cs = CsAccess::department();

        return $cs !== null && in_array($cs->id, $viewed->descendantIds(), true) ? $cs : null;
    }

    /** @return array<string, mixed>|null */
    public function forDepartment(Department $viewedDepartment, User $user, ?int $historyEmployeeId, ?string $historyPeriod, int $teamWeeks = 4): ?array
    {
        $teamWeeks = in_array($teamWeeks, [4, 12], true) ? $teamWeeks : 4;

        $csDepartment = $this->resolveCsDepartment($viewedDepartment);

        // Leadership (manager, assistant manager, CEO, Administrator) is the
        // gate, enforced here in the query layer: a member never receives
        // the team payload, whatever permissions their role carries.
        if ($csDepartment === null || ! CsAccess::leads($user, $csDepartment)) {
            return null;
        }

        $employees = CsAccess::scoredEmployees($csDepartment);

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
            'department' => $csDepartment->only(['id', 'name']),
            'employees' => $employees->map(fn (Employee $e) => ['id' => $e->id, 'full_name' => $e->full_name])->values(),
            'today' => $this->query->departmentToday($csDepartment),
            'awaitingReview' => $this->query->departmentAwaitingReview($csDepartment),
            'weekly' => $this->query->departmentWeekly($csDepartment),
            'exceptions' => $this->query->exceptions($csDepartment),
            'teamPerformance' => $this->query->teamPerformance($csDepartment, $teamWeeks),
            'teamWeeks' => $teamWeeks,
            'history' => $history,
            'pendingSales' => CsSalesRecord::query()
                ->whereHas('employee.user', fn ($q) => $q->where('department_id', $csDepartment->id))
                ->where('status', CsSalesRecord::STATUS_PENDING)
                ->with(['employee', 'evidence'])
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn (CsSalesRecord $r) => [
                    'id' => $r->id,
                    'employee_id' => $r->employee_id,
                    'employee_name' => $r->employee?->full_name,
                    'category' => $r->category,
                    'customer_identifier' => $r->customer_identifier,
                    'contact_channel' => $r->contact_channel,
                    'payment_reference' => $r->payment_reference,
                    'amount' => (float) $r->amount,
                    'currency' => $r->currency,
                    'week_start_date' => $r->week_start_date->toDateString(),
                    'evidence' => $r->evidence->map(fn ($e) => ['id' => $e->id, 'original_name' => $e->original_name])->all(),
                ])->all(),
            'platformAssignments' => CsPlatformAssignment::query()
                ->active()
                ->whereHas('employee.user', fn ($q) => $q->where('department_id', $csDepartment->id))
                ->with(['employee', 'website', 'backupEmployee'])
                ->get()
                ->map(fn (CsPlatformAssignment $a) => [
                    'id' => $a->id,
                    'employee_id' => $a->employee_id,
                    'employee_name' => $a->employee?->full_name,
                    'website_id' => $a->website_id,
                    'website_domain' => $a->website?->domain,
                    'country' => $a->country,
                    'backup_employee_name' => $a->backupEmployee?->full_name,
                    'effective_from' => $a->effective_from->toDateString(),
                ])->all(),
            'openComplaints' => CsComplaint::query()
                ->whereHas('employee.user', fn ($q) => $q->where('department_id', $csDepartment->id))
                ->where('status', CsComplaint::STATUS_OPEN)
                ->with(['employee', 'serviceInteraction', 'evidence'])
                ->orderByDesc('reported_at')
                ->limit(50)
                ->get()
                ->map(fn (CsComplaint $c) => [
                    'id' => $c->id,
                    'employee_id' => $c->employee_id,
                    'employee_name' => $c->employee?->full_name,
                    'description' => $c->description,
                    'reported_at' => $c->reported_at->toDateTimeString(),
                    'service_interaction_channel' => $c->serviceInteraction?->channel,
                    'evidence' => $c->evidence->map(fn ($e) => ['id' => $e->id, 'original_name' => $e->original_name])->all(),
                ])->all(),
            'dailyTemplates' => CsTaskTemplate::query()->active()->forCardType(CsTaskTemplate::CARD_TYPE_DAILY)->orderBy('position')->get(),
            'weeklyTemplates' => CsTaskTemplate::query()->active()->forCardType(CsTaskTemplate::CARD_TYPE_WEEKLY)->orderBy('position')->get(),
            'notifications' => $user->can('cs.settings.manage') && CsAccess::leadsBoard($user) ? [
                'hod' => $csDepartment->resolveHod(),
                'recipients' => DepartmentNotificationRecipient::query()->where('department_id', $csDepartment->id)->orderByDesc('is_active')->orderBy('email')->get(),
            ] : null,
            'calibrationEndsAt' => CompanySetting::current()->cs_calibration_ends_at?->toDateString(),
            'reportingCurrency' => CompanySetting::current()->cs_reporting_currency ?? 'KES',
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
