<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRecurringItemRequest;
use App\Models\Employee;
use App\Models\EmployeeRecurringItem;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EmployeeRecurringItemController extends Controller
{
    public function store(StoreEmployeeRecurringItemRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageCompensation', $employee);

        $item = $employee->recurringItems()->create($request->validated());
        AuditLogger::log($employee, 'recurring_item_added', [], $item->only(['kind', 'name', 'amount']));

        return back()->with('success', 'Recurring item added.');
    }

    public function update(StoreEmployeeRecurringItemRequest $request, Employee $employee, EmployeeRecurringItem $item): RedirectResponse
    {
        Gate::authorize('manageCompensation', $employee);
        abort_unless($item->employee_id === $employee->id, 404);

        $item->update($request->validated());

        return back()->with('success', 'Recurring item updated.');
    }

    public function destroy(Employee $employee, EmployeeRecurringItem $item): RedirectResponse
    {
        Gate::authorize('manageCompensation', $employee);
        abort_unless($item->employee_id === $employee->id, 404);

        $item->delete();

        return back()->with('success', 'Recurring item removed.');
    }
}
