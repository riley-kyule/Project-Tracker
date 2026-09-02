<?php

namespace App\Services\Hr\Leave;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Enforces the HR rule that two people in the same department may not be on
 * leave at the same time. Sick/emergency leave, requests flagged
 * `is_emergency`, and an approver holding an override role all bypass it.
 * All of it is toggleable via {@see LeaveSetting}.
 */
class LeaveOverlapChecker
{
    /**
     * Dates (Y-m-d) in [$from, $to] on which someone else in $employee's
     * department already has an active leave request that counts toward the
     * block. Empty when the rule is disabled.
     *
     * @return list<string>
     */
    public function blockedDates(Employee $employee, Carbon $from, Carbon $to, ?int $ignoreRequestId = null): array
    {
        $settings = LeaveSetting::current();

        if (! $settings->block_same_department_overlap || $employee->department_id === null) {
            return [];
        }

        $clashing = LeaveRequest::query()
            ->active()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->when($ignoreRequestId, fn ($q) => $q->whereKeyNot($ignoreRequestId))
            ->whereHas('employee', fn ($q) => $q
                ->where('department_id', $employee->department_id)
                ->whereKeyNot($employee->id))
            ->whereHas('leaveType', fn ($q) => $q->where('counts_toward_overlap_block', true))
            ->get(['id', 'start_date', 'end_date']);

        $blocked = [];

        foreach ($clashing as $request) {
            for ($cursor = $request->start_date->copy(); $cursor->lte($request->end_date); $cursor->addDay()) {
                if ($cursor->betweenIncluded($from, $to)) {
                    $blocked[$cursor->toDateString()] = true;
                }
            }
        }

        return array_keys($blocked);
    }

    /** Whether this request is allowed to ignore the overlap block entirely. */
    public function isExempt(LeaveRequest|LeaveType $subject, bool $isEmergency): bool
    {
        $settings = LeaveSetting::current();
        $type = $subject instanceof LeaveRequest ? $subject->leaveType : $subject;

        return $isEmergency
            || $type->is_emergency
            || in_array(strtoupper($type->code), $settings->exemptCodes(), true);
    }

    public function canOverride(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(LeaveSetting::current()->overrideRoles());
    }
}
