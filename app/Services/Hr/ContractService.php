<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Services\AuditLogger;
use App\Services\Hr\Leave\LeaveEntitlementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contract lifecycle. Renewing rolls the employee's contract dates forward
 * and resets their leave entitlement for the fresh period.
 */
class ContractService
{
    public function __construct(private readonly LeaveEntitlementService $entitlement) {}

    /**
     * @param  array{title?: string|null, employment_type?: string|null, start_date: string, end_date?: string|null, notes?: string|null}  $data
     */
    public function renew(Employee $employee, array $data): EmployeeContract
    {
        return DB::transaction(function () use ($employee, $data) {
            $start = Carbon::parse($data['start_date']);
            $end = isset($data['end_date']) && $data['end_date'] ? Carbon::parse($data['end_date']) : null;

            // Close the current open contract the day before the new one starts.
            $employee->contracts()
                ->whereNull('end_date')
                ->update(['end_date' => $start->copy()->subDay()->toDateString()]);

            $contract = $employee->contracts()->create([
                'title' => $data['title'] ?? $employee->job_title ?? 'Contract renewal',
                'department_id' => $employee->department_id,
                'employment_type' => $data['employment_type'] ?? $employee->employment_type,
                'start_date' => $start->toDateString(),
                'end_date' => $end?->toDateString(),
                'reason' => 'renewal',
                'notes' => $data['notes'] ?? null,
            ]);

            $old = $employee->only(['contract_start_date', 'contract_end_date', 'employment_status']);

            $employee->update([
                'contract_start_date' => $start->toDateString(),
                'contract_end_date' => $end?->toDateString(),
                'employment_status' => in_array($employee->employment_status, [Employee::STATUS_TERMINATED], true)
                    ? Employee::STATUS_ACTIVE
                    : $employee->employment_status,
            ]);

            AuditLogger::log($employee, 'contract_renewed', $old, [
                'contract_start_date' => $start->toDateString(),
                'contract_end_date' => $end?->toDateString(),
            ]);

            // Fresh leave entitlement for the new contract period. With accrual
            // off (the default) this is a clean reset to the configured day
            // allowances; prior-period balances stay as history.
            $this->entitlement->resetForNewPeriod($employee->refresh());

            return $contract;
        });
    }
}
