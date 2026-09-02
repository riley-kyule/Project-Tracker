<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreNextOfKinRequest;
use App\Models\Employee;
use App\Models\EmployeeNextOfKin;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EmployeeNextOfKinController extends Controller
{
    public function store(StoreNextOfKinRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        DB::transaction(function () use ($request, $employee) {
            $kin = $employee->nextOfKin()->create($request->validated());
            $this->enforceSinglePrimary($employee, $kin);
            AuditLogger::log($employee, 'next_of_kin_added', [], ['name' => $kin->name]);
        });

        return back()->with('success', 'Next of kin added.');
    }

    public function update(StoreNextOfKinRequest $request, Employee $employee, EmployeeNextOfKin $nextOfKin): RedirectResponse
    {
        Gate::authorize('update', $employee);
        abort_unless($nextOfKin->employee_id === $employee->id, 404);

        DB::transaction(function () use ($request, $employee, $nextOfKin) {
            $nextOfKin->update($request->validated());
            $this->enforceSinglePrimary($employee, $nextOfKin);
        });

        return back()->with('success', 'Next of kin updated.');
    }

    public function destroy(Employee $employee, EmployeeNextOfKin $nextOfKin): RedirectResponse
    {
        Gate::authorize('update', $employee);
        abort_unless($nextOfKin->employee_id === $employee->id, 404);

        $nextOfKin->delete();

        return back()->with('success', 'Next of kin removed.');
    }

    /** At most one contact per employee carries the primary flag. */
    private function enforceSinglePrimary(Employee $employee, EmployeeNextOfKin $kin): void
    {
        if ($kin->is_primary) {
            $employee->nextOfKin()->whereKeyNot($kin->id)->update(['is_primary' => false]);
        }
    }
}
