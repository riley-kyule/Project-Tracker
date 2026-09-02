<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Services\Hr\Leave\LeaveOverlapChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * JSON feed for the leave request form: which dates are public holidays and
 * which are blocked by the same-department overlap rule for a given employee.
 */
class LeaveCalendarController extends Controller
{
    public function index(Request $request, LeaveOverlapChecker $overlap): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->startOfDay();

        // Cap the window so this can't be used to scan the whole table.
        abort_if($from->diffInDays($to) > 400, 422, 'Range too wide.');

        $employee = $this->resolveEmployee($request, $validated['employee_id'] ?? null);

        $blocked = [];
        if ($employee) {
            $blocked = $overlap->blockedDates($employee, $from, $to);
        }

        return response()->json([
            'holidays' => PublicHoliday::datesBetween($from, $to),
            'blocked' => $blocked,
            'department_leave' => $employee && $employee->department_id
                ? LeaveRequest::query()
                    ->active()
                    ->overlapping($from->toDateString(), $to->toDateString())
                    ->whereHas('employee', fn ($q) => $q->where('department_id', $employee->department_id)->whereKeyNot($employee->id))
                    ->with('employee:id,first_name,middle_name,last_name')
                    ->get()
                    ->map(fn (LeaveRequest $r) => [
                        'employee' => $r->employee->full_name,
                        'start_date' => $r->start_date->toDateString(),
                        'end_date' => $r->end_date->toDateString(),
                    ])
                : [],
        ]);
    }

    private function resolveEmployee(Request $request, ?int $employeeId): ?Employee
    {
        if ($employeeId && $request->user()->can('hr.leave.manage')) {
            return Employee::find($employeeId);
        }

        return $request->user()->employee;
    }
}
