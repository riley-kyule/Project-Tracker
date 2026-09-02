<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    /** HR sees the whole queue; managers see their team's; everyone sees their own. */
    public function viewAny(User $user): bool
    {
        return $user->can('hr.leave.view') || $user->employee()->exists();
    }

    public function view(User $user, LeaveRequest $request): bool
    {
        if ($user->can('hr.leave.manage') || $this->isHrApprover($user)) {
            return true;
        }

        return $request->employee->user_id === $user->id || $this->managesRequester($user, $request);
    }

    public function create(User $user): bool
    {
        return $user->employee()->exists() || $user->can('hr.leave.manage');
    }

    /** Approve/reject: HR anywhere, or the requester's own line manager. Never your own. */
    public function decide(User $user, LeaveRequest $request): bool
    {
        if ($request->employee->user_id === $user->id) {
            return false;
        }

        if ($user->can('hr.leave.manage')) {
            return true;
        }

        return $user->can('hr.leave.approve') && $this->managesRequester($user, $request);
    }

    /** The requester withdrawing their own still-pending/future request. */
    public function cancel(User $user, LeaveRequest $request): bool
    {
        return $request->employee->user_id === $user->id || $user->can('hr.leave.manage');
    }

    public function adjustBalances(User $user): bool
    {
        return $user->can('hr.leave.manage');
    }

    private function isHrApprover(User $user): bool
    {
        return $user->can('hr.leave.view');
    }

    private function managesRequester(User $user, LeaveRequest $request): bool
    {
        $employee = $request->employee;

        if ($employee->manager?->user_id === $user->id) {
            return true;
        }

        if ($employee->department_id === null) {
            return false;
        }

        return Department::query()
            ->where('id', $employee->department_id)
            ->where(fn ($q) => $q->where('manager_id', $user->id)->orWhere('assistant_manager_id', $user->id))
            ->exists();
    }
}
