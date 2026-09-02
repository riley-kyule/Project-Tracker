<?php

namespace App\Services\Hr\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Provisions and resets {@see LeaveBalance} rows. With accrual off (the
 * default) each balance is simply the type's fixed day allowance for the
 * current entitlement period; "Contract renewed" starts a fresh period.
 */
class LeaveEntitlementService
{
    /**
     * The [start, end] of the entitlement period an employee is currently in.
     *
     * @return array{0: Carbon, 1: Carbon|null}
     */
    public function currentPeriod(Employee $employee): array
    {
        $settings = LeaveSetting::current();

        if ($settings->entitlement_basis === 'calendar_year') {
            $startMonth = max(1, min(12, (int) $settings->leave_year_start_month));
            $start = Carbon::create(now()->year, $startMonth, 1)->startOfDay();
            if ($start->isFuture()) {
                $start->subYear();
            }

            return [$start, $start->copy()->addYear()->subDay()];
        }

        // contract_period
        $start = $employee->contract_start_date
            ? $employee->contract_start_date->copy()
            : ($employee->date_hired ? $employee->date_hired->copy() : Carbon::create(now()->year, 1, 1));

        return [$start, $employee->contract_end_date?->copy()];
    }

    /** Entitled days for a type — the type's own allowance, or the org default for annual leave. */
    private function entitledDaysFor(LeaveType $type): float
    {
        if ($type->default_days !== null) {
            return (float) $type->default_days;
        }

        if ($type->code === 'ANNUAL') {
            return (float) LeaveSetting::current()->default_annual_days;
        }

        return 0.0;
    }

    /**
     * Ensure a balance row exists for every active, entitlement-based type for
     * the employee's current period. Never lowers a row that already has
     * activity — use {@see resetForNewPeriod()} for a clean slate.
     */
    public function provisionForCurrentPeriod(Employee $employee): void
    {
        [$start, $end] = $this->currentPeriod($employee);
        $start = $start->copy()->startOfDay();

        DB::transaction(function () use ($employee, $start, $end) {
            LeaveType::query()->active()->get()->each(function (LeaveType $type) use ($employee, $start, $end) {
                if ($type->accrual_method === 'none' && $type->default_days === null) {
                    return; // uncapped, as-approved (e.g. compassionate) — no balance to track
                }

                LeaveBalance::query()->firstOrCreate(
                    ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'period_start' => $start],
                    ['period_end' => $end, 'entitled_days' => $this->entitledDaysFor($type)],
                );
            });
        });
    }

    /**
     * Contract renewal / new leave year: open fresh balance rows for the new
     * period at full entitlement. Prior-period rows are left intact as history.
     * Carry-over is applied only when enabled in settings.
     */
    public function resetForNewPeriod(Employee $employee): void
    {
        [$start, $end] = $this->currentPeriod($employee);
        $start = $start->copy()->startOfDay();
        $settings = LeaveSetting::current();

        DB::transaction(function () use ($employee, $start, $end, $settings) {
            LeaveType::query()->active()->get()->each(function (LeaveType $type) use ($employee, $start, $end, $settings) {
                if ($type->accrual_method === 'none' && $type->default_days === null) {
                    return;
                }

                $carry = 0.0;
                if ($settings->carryover_enabled && $type->code === 'ANNUAL') {
                    $previous = LeaveBalance::query()
                        ->where('employee_id', $employee->id)
                        ->where('leave_type_id', $type->id)
                        ->where('period_start', '<', $start)
                        ->orderByDesc('period_start')
                        ->first();

                    if ($previous) {
                        $carry = min($previous->available_days, (float) $settings->max_carryover_days);
                        $carry = max($carry, 0.0);
                    }
                }

                LeaveBalance::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'period_start' => $start],
                    [
                        'period_end' => $end,
                        'entitled_days' => $this->entitledDaysFor($type),
                        'carried_over_days' => $carry,
                        'accrued_days' => 0,
                        'taken_days' => 0,
                        'pending_days' => 0,
                        'adjustment_days' => 0,
                        'adjustment_reason' => null,
                    ],
                );
            });
        });
    }

    /** The employee's balance row for a type in their current period, provisioning if absent. */
    public function balanceFor(Employee $employee, LeaveType $type): ?LeaveBalance
    {
        [$start] = $this->currentPeriod($employee);

        $find = fn () => LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereDate('period_start', $start->toDateString())
            ->first();

        $balance = $find();

        if ($balance === null && ($type->default_days !== null || $type->accrual_method !== 'none')) {
            $this->provisionForCurrentPeriod($employee);
            $balance = $find();
        }

        return $balance;
    }
}
