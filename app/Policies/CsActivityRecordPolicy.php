<?php

namespace App\Policies;

use App\Models\CsActivityRecord;
use App\Models\User;
use App\Services\Cs\CsAccess;

/** §5.1 daily sales activity records — own employee, or the department's leadership. */
class CsActivityRecordPolicy
{
    public function view(User $user, CsActivityRecord $record): bool
    {
        return ($record->employee !== null && CsAccess::owns($user, $record->employee))
            || ($record->employee !== null && CsAccess::leadsEmployee($user, $record->employee));
    }
}
