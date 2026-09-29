<?php

/*
 * Sample records for tests/e2e/smoke.spec.ts, run through `artisan tinker`
 * against the throwaway e2e database only (the spec sets DB_DATABASE). Gives
 * every record page (a ticket, project, employee, asset, leave request,
 * payroll run, payslip-less period and performance review) something to show.
 * Idempotent enough to re-run: it only ever adds rows.
 */

use App\Models\Asset;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\PerformanceCycle;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Hr\Leave\LeaveRequestService;
use App\Services\Hr\PerformanceService;

$admin = User::where('email', 'admin@ewms.test')->firstOrFail();
$employeeUser = User::where('email', 'employee@ewms.test')->firstOrFail();
$department = Department::query()->orderBy('id')->firstOrFail();

$adminEmployee = $admin->employee ?? Employee::factory()->create(['user_id' => $admin->id, 'department_id' => $department->id]);
$employee = $employeeUser->employee ?? Employee::factory()->create([
    'user_id' => $employeeUser->id,
    'department_id' => $employeeUser->department_id ?? $department->id,
    'manager_id' => $adminEmployee->id,
]);

Ticket::factory()->create(['requester_id' => $employeeUser->id]);
Project::factory()->create(['owner_id' => $admin->id]);
Asset::factory()->create();

try {
    app(LeaveRequestService::class)->submit($employee, [
        'leave_type_id' => LeaveType::query()->active()->where('code', 'ANNUAL')->value('id') ?? LeaveType::query()->active()->value('id'),
        'start_date' => now()->addWeeks(3)->startOfWeek()->toDateString(),
        'end_date' => now()->addWeeks(3)->startOfWeek()->addDay()->toDateString(),
        'reason' => 'Smoke test',
        'is_emergency' => true,
    ], $employeeUser);
} catch (Throwable $e) {
    echo 'leave skipped: '.$e->getMessage().PHP_EOL;
}

PayrollPeriod::query()->firstOrCreate(['year' => (int) now()->format('Y'), 'month' => (int) now()->format('n')], [
    'label' => now()->format('F Y'),
    'start_date' => now()->startOfMonth()->toDateString(),
    'end_date' => now()->endOfMonth()->toDateString(),
    'pay_date' => now()->endOfMonth()->toDateString(),
    'status' => PayrollPeriod::STATUS_DRAFT,
]);

$cycle = PerformanceCycle::query()->firstOrCreate(['name' => 'Smoke cycle'], [
    'type' => 'annual',
    'period_start' => now()->startOfYear()->toDateString(),
    'period_end' => now()->endOfYear()->toDateString(),
    'status' => 'draft',
    'created_by' => $admin->id,
]);
app(PerformanceService::class)->activate($cycle);

echo 'seeded'.PHP_EOL;
