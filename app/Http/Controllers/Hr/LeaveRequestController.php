<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreLeaveRequestRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Services\Hr\Leave\LeaveRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        $user = $request->user();
        $canManage = $user->can('hr.leave.manage');

        $month = Carbon::parse($request->query('month', now()->toDateString()))->startOfMonth();
        $calFrom = $month->copy()->startOfWeek();
        $calTo = $month->copy()->endOfMonth()->endOfWeek();

        $decidable = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->with(['employee:id,first_name,middle_name,last_name,department_id', 'leaveType:id,name,code,color'])
            ->orderBy('start_date')
            ->get()
            ->filter(fn (LeaveRequest $r) => $user->can('decide', $r))
            ->values();

        $calendarLeave = LeaveRequest::query()
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])
            ->where('start_date', '<=', $calTo->toDateString())
            ->where('end_date', '>=', $calFrom->toDateString())
            ->with(['employee:id,first_name,middle_name,last_name,department_id', 'leaveType:id,name,code,color'])
            ->get()
            ->map(fn (LeaveRequest $r) => [
                'id' => $r->id,
                'employee' => $r->employee->full_name,
                'department_id' => $r->employee->department_id,
                'type' => $r->leaveType->name,
                'code' => $r->leaveType->code,
                'color' => $r->leaveType->color,
                'status' => $r->status,
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date->toDateString(),
            ]);

        return Inertia::render('hr/leave/index', [
            'month' => $month->toDateString(),
            'calendarRange' => ['from' => $calFrom->toDateString(), 'to' => $calTo->toDateString()],
            'pending' => $decidable->map(fn (LeaveRequest $r) => $this->cardData($r)),
            'calendarLeave' => $calendarLeave,
            'holidays' => PublicHoliday::datesBetween($calFrom, $calTo),
            'canManage' => $canManage,
            'leaveTypes' => LeaveType::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'is_emergency', 'requires_document', 'gender_eligibility']),
            'employees' => $canManage
                ? Employee::query()->active()->orderBy('first_name')->get(['id', 'first_name', 'middle_name', 'last_name'])
                    ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name])
                : [],
        ]);
    }

    public function store(StoreLeaveRequestRequest $request, LeaveRequestService $service): RedirectResponse
    {
        Gate::authorize('create', LeaveRequest::class);

        $employee = $this->resolveEmployee($request);

        $service->submit($employee, $request->validated(), $request->user());

        return back()->with('success', 'Leave request submitted.');
    }

    public function show(LeaveRequest $leaveRequest): Response
    {
        Gate::authorize('view', $leaveRequest);

        $leaveRequest->load([
            'employee:id,first_name,middle_name,last_name,department_id',
            'leaveType:id,name,code',
            'handoverTo:id,first_name,middle_name,last_name',
            'approvals.approver:id,name',
        ]);

        return Inertia::render('hr/leave/show', [
            'request' => [
                ...$this->cardData($leaveRequest),
                'contact_during_leave' => $leaveRequest->contact_during_leave,
                'handover_to' => $leaveRequest->handoverTo?->full_name,
                'decision_note' => $leaveRequest->decision_note,
                'overlap_override_reason' => $leaveRequest->overlap_override_reason,
                'approvals' => $leaveRequest->approvals->map(fn ($a) => [
                    'approver' => $a->approver?->name,
                    'action' => $a->action,
                    'note' => $a->note,
                    'acted_at' => $a->acted_at,
                ]),
            ],
            'canDecide' => request()->user()->can('decide', $leaveRequest),
            'canCancel' => request()->user()->can('cancel', $leaveRequest),
        ]);
    }

    public function cancel(LeaveRequest $leaveRequest, LeaveRequestService $service): RedirectResponse
    {
        Gate::authorize('cancel', $leaveRequest);

        $service->cancel($leaveRequest, request()->user());

        return back()->with('success', 'Leave request cancelled.');
    }

    private function resolveEmployee(Request $request): Employee
    {
        $onBehalfId = $request->integer('employee_id');

        if ($onBehalfId && $request->user()->can('hr.leave.manage')) {
            return Employee::findOrFail($onBehalfId);
        }

        return $request->user()->employee()->firstOrFail();
    }

    private function cardData(LeaveRequest $r): array
    {
        return [
            'id' => $r->id,
            'employee' => $r->employee->full_name,
            'type' => $r->leaveType->name,
            'code' => $r->leaveType->code,
            'status' => $r->status,
            'days' => (float) $r->days,
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'is_emergency' => $r->is_emergency,
            'reason' => $r->reason,
        ];
    }
}
