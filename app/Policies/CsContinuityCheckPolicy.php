<?php

namespace App\Policies;

use App\Models\CsContinuityCheck;
use App\Models\User;
use App\Services\Cs\CsAccess;

/** §5.2 daily continuity checks — own employee, or the department's leadership. */
class CsContinuityCheckPolicy
{
    public function view(User $user, CsContinuityCheck $check): bool
    {
        return ($check->employee !== null && CsAccess::owns($user, $check->employee))
            || ($check->employee !== null && CsAccess::leadsEmployee($user, $check->employee));
    }
}
