<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RenewContractRequest;
use App\Http\Requests\Hr\StoreEmployeeContractRequest;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Services\AuditLogger;
use App\Services\Hr\ContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EmployeeContractController extends Controller
{
    /**
     * "Contract renewed" — closes the current contract, opens a fresh one for
     * the new period, rolls the employee's contract dates forward and (via
     * {@see ContractService}) resets their leave entitlement to the configured
     * defaults for the new contract period.
     */
    public function renew(RenewContractRequest $request, Employee $employee, ContractService $service): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $service->renew($employee, $request->validated());

        return back()->with('success', 'Contract renewed. Leave entitlement has been reset for the new period.');
    }

    public function store(StoreEmployeeContractRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $contract = $employee->contracts()->create($request->validated());

        AuditLogger::log($employee, 'contract_added', [], $contract->only(['title', 'start_date', 'end_date']));

        return back()->with('success', 'Contract added.');
    }

    public function update(StoreEmployeeContractRequest $request, Employee $employee, EmployeeContract $contract): RedirectResponse
    {
        Gate::authorize('update', $employee);
        abort_unless($contract->employee_id === $employee->id, 404);

        $contract->update($request->validated());

        return back()->with('success', 'Contract updated.');
    }

    public function destroy(Employee $employee, EmployeeContract $contract): RedirectResponse
    {
        Gate::authorize('update', $employee);
        abort_unless($contract->employee_id === $employee->id, 404);

        $contract->delete();

        return back()->with('success', 'Contract removed.');
    }
}
