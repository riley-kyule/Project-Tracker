<?php

namespace App\Policies;

use App\Models\CompanySetting;
use App\Models\PayrollPeriod;
use App\Models\User;

class PayrollPeriodPolicy
{
    /**
     * The HR Manager runs and dispatches payroll on their own unless the
     * "require a second sign-off" toggle is on, in which case approving and
     * dispatching need a CEO/Administrator (`hr.payroll.approve`).
     */
    private function payrollAuthority(User $user): bool
    {
        return CompanySetting::current()->payroll_requires_second_approval
            ? $user->can('hr.payroll.approve')
            : $user->can('hr.payroll.process');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('hr.payroll.view');
    }

    public function view(User $user, PayrollPeriod $period): bool
    {
        return $user->can('hr.payroll.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.payroll.process');
    }

    public function process(User $user, PayrollPeriod $period): bool
    {
        return $user->can('hr.payroll.process') && $period->isEditable();
    }

    /** Only a distinct step when a second sign-off is required. */
    public function approve(User $user, PayrollPeriod $period): bool
    {
        return CompanySetting::current()->payroll_requires_second_approval
            && $period->status === PayrollPeriod::STATUS_REVIEW
            && $user->can('hr.payroll.approve');
    }

    /** Send the payslips out. Works straight from "review" when no second sign-off is needed. */
    public function markPaid(User $user, PayrollPeriod $period): bool
    {
        $allowedFrom = CompanySetting::current()->payroll_requires_second_approval
            ? [PayrollPeriod::STATUS_APPROVED]
            : [PayrollPeriod::STATUS_REVIEW, PayrollPeriod::STATUS_APPROVED];

        return in_array($period->status, $allowedFrom, true) && $this->payrollAuthority($user);
    }

    public function export(User $user, PayrollPeriod $period): bool
    {
        return $user->can('hr.payroll.view');
    }
}
