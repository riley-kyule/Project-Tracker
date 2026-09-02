<?php

namespace App\Policies;

use App\Models\Payslip;
use App\Models\User;

class PayslipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.payroll.view');
    }

    /** Payroll staff see any payslip; an employee sees only their own. */
    public function view(User $user, Payslip $payslip): bool
    {
        return $user->can('hr.payroll.view') || $payslip->employee?->user_id === $user->id;
    }
}
