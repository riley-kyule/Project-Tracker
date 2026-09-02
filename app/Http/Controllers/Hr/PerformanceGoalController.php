<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePerformanceGoalRequest;
use App\Models\Employee;
use App\Models\PerformanceGoal;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PerformanceGoalController extends Controller
{
    public function store(StorePerformanceGoalRequest $request, Employee $employee): RedirectResponse
    {
        abort_unless($this->canManage($request, $employee), 403);

        $goal = $employee->goals()->create($request->validated());
        AuditLogger::log($employee, 'goal_added', [], $goal->only(['title']));

        return back()->with('success', 'Goal added.');
    }

    public function update(StorePerformanceGoalRequest $request, Employee $employee, PerformanceGoal $goal): RedirectResponse
    {
        abort_unless($this->canManage($request, $employee), 403);
        abort_unless($goal->employee_id === $employee->id, 404);

        $goal->update($request->validated());

        return back()->with('success', 'Goal updated.');
    }

    public function destroy(Request $request, Employee $employee, PerformanceGoal $goal): RedirectResponse
    {
        abort_unless($this->canManage($request, $employee), 403);
        abort_unless($goal->employee_id === $employee->id, 404);

        $goal->delete();

        return back()->with('success', 'Goal removed.');
    }

    /** HR performance managers, the employee's own line manager, or the employee. */
    private function canManage(Request $request, Employee $employee): bool
    {
        $user = $request->user();

        return $user->can('hr.performance.manage')
            || $employee->manager?->user_id === $user->id
            || $employee->user_id === $user->id;
    }
}
