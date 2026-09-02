<?php

namespace App\Policies;

use App\Models\PayrollPeriod;
use App\Models\User;

class PayrollPeriodPolicy
{
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

    public function approve(User $user, PayrollPeriod $period): bool
    {
        return $user->can('hr.payroll.approve') && $period->status === PayrollPeriod::STATUS_REVIEW;
    }

    public function markPaid(User $user, PayrollPeriod $period): bool
    {
        return $user->can('hr.payroll.approve') && $period->status === PayrollPeriod::STATUS_APPROVED;
    }

    public function export(User $user, PayrollPeriod $period): bool
    {
        return $user->can('hr.payroll.view');
    }
}
