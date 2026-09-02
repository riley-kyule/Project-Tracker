<?php

namespace App\Policies;

use App\Models\StatutoryRateSet;
use App\Models\User;

class StatutoryRateSetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.payroll.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.payroll.process');
    }

    public function update(User $user, StatutoryRateSet $rateSet): bool
    {
        return $user->can('hr.payroll.process');
    }
}
