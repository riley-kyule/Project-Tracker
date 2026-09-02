<?php

namespace App\Services\Hr\Leave;

use App\Models\Employee;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\Hr\LeaveRequestDecided;
use App\Notifications\Hr\LeaveRequestSubmitted;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The lifecycle of a leave request: it validates against balance, notice and
 * the same-department overlap rule, keeps {@see LeaveBalance} in
 * step (pending → taken), and notifies the line manager, HR and the requester
 * at each step.
 */
class LeaveRequestService
{
    public function __construct(
        private readonly LeaveCalculator $calculator,
        private readonly LeaveOverlapChecker $overlap,
        private readonly LeaveEntitlementService $entitlement,
    ) {}

    /**
     * @param  array{leave_type_id:int,start_date:string,end_date:string,half_day_start?:bool,half_day_end?:bool,reason?:string|null,contact_during_leave?:string|null,handover_to?:int|null,is_emergency?:bool}  $data
     */
    public function submit(Employee $employee, array $data, User $actor): LeaveRequest
    {
        $type = LeaveType::query()->active()->findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $isEmergency = (bool) ($data['is_emergency'] ?? false);

        if (! $type->isAvailableTo($employee)) {
            throw ValidationException::withMessages(['leave_type_id' => "This leave type isn't available to this employee."]);
        }

        $days = $this->calculator->workingDays($start, $end, (bool) ($data['half_day_start'] ?? false), (bool) ($data['half_day_end'] ?? false));

        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => 'That range contains no working days.']);
        }

        $this->assertNotice($type, $start, $isEmergency);
        $this->assertNoActiveOverlapForSelf($employee, $start, $end);

        $blocked = $this->overlap->isExempt($type, $isEmergency)
            ? []
            : $this->overlap->blockedDates($employee, $start, $end);

        if ($blocked !== []) {
            throw ValidationException::withMessages([
                'start_date' => 'A colleague in this department is already on leave then ('.implode(', ', $blocked).'). '
                    .'Mark it as emergency leave, or ask HR to override.',
            ]);
        }

        $balance = $this->entitlement->balanceFor($employee, $type);

        if ($balance !== null && $days > $balance->available_days + 1e-6) {
            throw ValidationException::withMessages([
                'leave_type_id' => "Only {$balance->available_days} day(s) of {$type->name} remain.",
            ]);
        }

        return DB::transaction(function () use ($employee, $type, $start, $end, $days, $data, $isEmergency, $balance) {
            $request = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'half_day_start' => (bool) ($data['half_day_start'] ?? false),
                'half_day_end' => (bool) ($data['half_day_end'] ?? false),
                'days' => $days,
                'reason' => $data['reason'] ?? null,
                'contact_during_leave' => $data['contact_during_leave'] ?? null,
                'handover_to' => $data['handover_to'] ?? null,
                'is_emergency' => $isEmergency,
                'status' => LeaveRequest::STATUS_PENDING,
                'current_approver_id' => $employee->manager?->user_id,
            ]);

            if ($balance !== null) {
                $balance->increment('pending_days', $days);
            }

            AuditLogger::log($request, 'leave_requested', [], ['type' => $type->code, 'days' => $days, 'from' => $start->toDateString()]);

            Notification::send($this->approverAudience($employee), new LeaveRequestSubmitted($request));

            return $request;
        });
    }

    public function decide(LeaveRequest $request, User $approver, bool $approve, ?string $note, ?string $overrideReason = null): LeaveRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => 'This request has already been decided.']);
        }

        return DB::transaction(function () use ($request, $approver, $approve, $note, $overrideReason) {
            $request->loadMissing('employee', 'leaveType');
            $balance = $this->entitlement->balanceFor($request->employee, $request->leaveType);

            if ($approve) {
                // Re-check the overlap rule at decision time; an override needs a role + reason.
                $blocked = $this->overlap->isExempt($request, $request->is_emergency)
                    ? []
                    : $this->overlap->blockedDates($request->employee, $request->start_date, $request->end_date, $request->id);

                if ($blocked !== [] && ! $this->overlap->canOverride($approver)) {
                    throw ValidationException::withMessages(['overlap' => 'A colleague is already on leave then; only HR/CEO can override.']);
                }

                $request->update([
                    'status' => LeaveRequest::STATUS_APPROVED,
                    'decided_by' => $approver->id,
                    'decided_at' => now(),
                    'decision_note' => $note,
                    'overlap_overridden_by' => $blocked !== [] ? $approver->id : null,
                    'overlap_override_reason' => $blocked !== [] ? $overrideReason : null,
                ]);

                if ($balance !== null) {
                    $balance->decrement('pending_days', (float) $request->days);
                    $balance->increment('taken_days', (float) $request->days);
                }
            } else {
                $request->update([
                    'status' => LeaveRequest::STATUS_REJECTED,
                    'decided_by' => $approver->id,
                    'decided_at' => now(),
                    'decision_note' => $note,
                ]);

                if ($balance !== null) {
                    $balance->decrement('pending_days', (float) $request->days);
                }
            }

            LeaveApproval::create([
                'leave_request_id' => $request->id,
                'approver_id' => $approver->id,
                'level' => 1,
                'action' => $approve ? 'approved' : 'rejected',
                'note' => $note,
                'acted_at' => now(),
            ]);

            AuditLogger::log($request, $approve ? 'leave_approved' : 'leave_rejected', [], ['by' => $approver->name]);

            if ($request->employee->user) {
                $request->employee->user->notify(new LeaveRequestDecided($request));
            }

            return $request;
        });
    }

    /** The requester withdrawing / cancelling their own request. */
    public function cancel(LeaveRequest $request, User $actor): LeaveRequest
    {
        if (! in_array($request->status, [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages(['status' => "This request can't be cancelled."]);
        }

        if ($request->status === LeaveRequest::STATUS_APPROVED && $request->start_date->isPast()) {
            throw ValidationException::withMessages(['status' => 'Leave that has already started cannot be cancelled here — contact HR.']);
        }

        return DB::transaction(function () use ($request) {
            $request->loadMissing('employee', 'leaveType');
            $balance = $this->entitlement->balanceFor($request->employee, $request->leaveType);
            $wasApproved = $request->status === LeaveRequest::STATUS_APPROVED;

            $request->update([
                'status' => $wasApproved ? LeaveRequest::STATUS_CANCELLED : LeaveRequest::STATUS_WITHDRAWN,
            ]);

            if ($balance !== null) {
                $balance->decrement($wasApproved ? 'taken_days' : 'pending_days', (float) $request->days);
            }

            AuditLogger::log($request, 'leave_cancelled', [], []);

            return $request;
        });
    }

    private function assertNotice(LeaveType $type, Carbon $start, bool $isEmergency): void
    {
        if ($isEmergency || $type->is_emergency) {
            return;
        }

        $required = $type->min_notice_days ?? LeaveSetting::current()->min_notice_days;

        if ($required > 0 && $start->lt(now()->startOfDay()->addDays($required))) {
            throw ValidationException::withMessages([
                'start_date' => "{$type->name} needs at least {$required} day(s) notice.",
            ]);
        }
    }

    private function assertNoActiveOverlapForSelf(Employee $employee, Carbon $start, Carbon $end): void
    {
        $exists = $employee->leaveRequests()
            ->active()
            ->overlapping($start->toDateString(), $end->toDateString())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['start_date' => 'You already have a leave request covering some of these dates.']);
        }
    }

    /** Line manager + everyone who can action leave (HR). */
    private function approverAudience(Employee $employee): Collection
    {
        $recipients = User::query()->permission('hr.leave.approve')->get();

        if ($employee->manager?->user) {
            $recipients->push($employee->manager->user);
        }

        return $recipients->unique('id')->values();
    }
}
