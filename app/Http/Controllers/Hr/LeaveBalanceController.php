<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\AdjustLeaveBalanceRequest;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Services\AuditLogger;
use App\Services\Hr\Leave\LeaveEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeaveBalanceController extends Controller
{
    public function index(LeaveEntitlementService $entitlement): Response
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        $employees = Employee::query()->active()
            ->with(['department:id,name', 'leaveBalances.leaveType:id,name,code'])
            ->orderBy('first_name')
            ->get();

        return Inertia::render('hr/leave/balances', [
            'employees' => $employees->map(function (Employee $e) use ($entitlement) {
                [$periodStart] = $entitlement->currentPeriod($e);

                return [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'department' => $e->department?->name,
                    'period_start' => $periodStart->toDateString(),
                    'balances' => $e->leaveBalances
                        ->filter(fn (LeaveBalance $b) => $b->period_start?->toDateString() === $periodStart->toDateString())
                        ->map(fn (LeaveBalance $b) => [
                            'id' => $b->id,
                            'type' => $b->leaveType->name,
                            'code' => $b->leaveType->code,
                            'entitled_days' => (float) $b->entitled_days,
                            'carried_over_days' => (float) $b->carried_over_days,
                            'taken_days' => (float) $b->taken_days,
                            'pending_days' => (float) $b->pending_days,
                            'adjustment_days' => (float) $b->adjustment_days,
                            'available_days' => $b->available_days,
                        ])->values(),
                ];
            }),
        ]);
    }

    public function provision(Employee $employee, LeaveEntitlementService $entitlement): RedirectResponse
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        $entitlement->provisionForCurrentPeriod($employee);

        return back()->with('success', "Leave balances provisioned for {$employee->full_name}.");
    }

    public function update(AdjustLeaveBalanceRequest $request, LeaveBalance $balance): RedirectResponse
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        $old = $balance->only(['entitled_days', 'adjustment_days', 'adjustment_reason']);
        $balance->update($request->validated());

        AuditLogger::log($balance, 'leave_balance_adjusted', $old, $balance->only(['entitled_days', 'adjustment_days', 'adjustment_reason']));

        return back()->with('success', 'Balance adjusted.');
    }
}
