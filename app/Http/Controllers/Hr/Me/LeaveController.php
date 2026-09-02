<?php

namespace App\Http\Controllers\Hr\Me;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hr\LeaveRequestController;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\Leave\LeaveEntitlementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee self-service leave: current balances, request history, and the
 * form to apply. Submitting posts to {@see LeaveRequestController}.
 */
class LeaveController extends Controller
{
    public function index(Request $request, LeaveEntitlementService $entitlement): Response
    {
        $employee = $request->user()->employee()->with('department:id,name')->firstOrFail();
        $entitlement->provisionForCurrentPeriod($employee);
        [$periodStart, $periodEnd] = $entitlement->currentPeriod($employee);

        $balances = $employee->leaveBalances()
            ->whereDate('period_start', $periodStart->toDateString())
            ->with('leaveType:id,name,code')
            ->get()
            ->map(fn ($b) => [
                'type' => $b->leaveType->name,
                'code' => $b->leaveType->code,
                'entitled_days' => (float) $b->entitled_days,
                'carried_over_days' => (float) $b->carried_over_days,
                'taken_days' => (float) $b->taken_days,
                'pending_days' => (float) $b->pending_days,
                'available_days' => $b->available_days,
            ]);

        $requests = $employee->leaveRequests()
            ->with('leaveType:id,name,code')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (LeaveRequest $r) => [
                'id' => $r->id,
                'type' => $r->leaveType->name,
                'status' => $r->status,
                'days' => (float) $r->days,
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date->toDateString(),
                'is_emergency' => $r->is_emergency,
                'decision_note' => $r->decision_note,
                'can_cancel' => $request->user()->can('cancel', $r)
                    && in_array($r->status, [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED], true)
                    && $r->start_date->isFuture(),
            ]);

        return Inertia::render('hr/me/leave', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'gender' => $employee->gender,
                'department' => $employee->department?->name,
                'period' => ['start' => $periodStart->toDateString(), 'end' => $periodEnd?->toDateString()],
            ],
            'balances' => $balances,
            'requests' => $requests,
            'leaveTypes' => LeaveType::query()->active()->orderBy('name')
                ->get(['id', 'name', 'code', 'is_emergency', 'requires_document', 'gender_eligibility', 'requires_approval']),
        ]);
    }
}
