<?php

namespace App\Policies;

use App\Models\CsContinuityIssue;
use App\Models\User;
use App\Services\Cs\CsAccess;

/** §5.2 continuity issue tracker — own employee, or the department's leadership. */
class CsContinuityIssuePolicy
{
    public function view(User $user, CsContinuityIssue $issue): bool
    {
        return ($issue->employee !== null && CsAccess::owns($user, $issue->employee))
            || ($issue->employee !== null && CsAccess::leadsEmployee($user, $issue->employee));
    }
}
