<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeCompensationRequest;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EmployeeCompensationController extends Controller
{
    public function store(StoreEmployeeCompensationRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageCompensation', $employee);

        $record = $employee->compensation()->create([
            ...$request->validated(),
            'currency' => $request->input('currency', 'KES'),
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::log($employee, 'compensation_added', [], ['basic_salary' => $record->basic_salary, 'from' => $record->effective_from->toDateString()]);

        return back()->with('success', 'Compensation record added.');
    }

    public function update(StoreEmployeeCompensationRequest $request, Employee $employee, EmployeeCompensation $compensation): RedirectResponse
    {
        Gate::authorize('manageCompensation', $employee);
        abort_unless($compensation->employee_id === $employee->id, 404);

        $old = $compensation->only(['effective_from', 'basic_salary', 'allowances']);
        $compensation->update($request->validated());
        AuditLogger::log($employee, 'compensation_updated', $old, $compensation->only(['effective_from', 'basic_salary', 'allowances']));

        return back()->with('success', 'Compensation record updated.');
    }

    public function destroy(Employee $employee, EmployeeCompensation $compensation): RedirectResponse
    {
        Gate::authorize('manageCompensation', $employee);
        abort_unless($compensation->employee_id === $employee->id, 404);

        $compensation->delete();

        return back()->with('success', 'Compensation record removed.');
    }
}
