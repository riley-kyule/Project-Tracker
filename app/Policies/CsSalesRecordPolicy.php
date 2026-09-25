<?php

namespace App\Policies;

use App\Models\CsSalesRecord;
use App\Models\User;
use App\Services\Cs\CsAccess;

/**
 * Customer Service Board Requirements Specification v1.0 §4/§10: an
 * employee records their own sales; only the department's leadership
 * (manager, assistant manager, CEO, Administrator) confirms clearance and
 * attribution. Department comes from the employee's user account.
 */
class CsSalesRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cs.cards.view') || CsAccess::leadsBoard($user);
    }

    public function view(User $user, CsSalesRecord $record): bool
    {
        return ($record->employee !== null && CsAccess::owns($user, $record->employee))
            || ($record->employee !== null && CsAccess::leads($user, $record->employee->user?->department));
    }

    public function create(User $user): bool
    {
        return $user->can('cs.cards.update') && $user->isCsEmployee();
    }

    public function approve(User $user, CsSalesRecord $record): bool
    {
        return $record->employee !== null && CsAccess::leadsEmployee($user, $record->employee);
    }
}
