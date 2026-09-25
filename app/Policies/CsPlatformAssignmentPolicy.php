<?php

namespace App\Policies;

use App\Models\CsPlatformAssignment;
use App\Models\User;
use App\Services\Cs\CsAccess;

/** Customer Service Board Requirements Specification v1.0 §5.2: the department's leadership owns platform/country assignments. */
class CsPlatformAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return CsAccess::leadsBoard($user);
    }

    public function create(User $user): bool
    {
        return CsAccess::leadsBoard($user);
    }

    public function update(User $user, CsPlatformAssignment $assignment): bool
    {
        return $assignment->employee !== null && CsAccess::leadsEmployee($user, $assignment->employee);
    }

    public function delete(User $user, CsPlatformAssignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
