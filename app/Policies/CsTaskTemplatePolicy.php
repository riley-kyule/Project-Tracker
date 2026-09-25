<?php

namespace App\Policies;

use App\Models\CsTaskTemplate;
use App\Models\User;
use App\Services\Cs\CsAccess;

/**
 * The template library, Customer Service Board Requirements Specification
 * v1.0 §10 workflow rule 1. Needs the cs.templates.manage permission AND
 * leadership of the Customer Service department, so a manager of some other
 * department who holds the role-level permission cannot edit it.
 */
class CsTaskTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cs.templates.manage') && CsAccess::leadsBoard($user);
    }

    public function create(User $user): bool
    {
        return $user->can('cs.templates.manage') && CsAccess::leadsBoard($user);
    }

    public function update(User $user, CsTaskTemplate $template): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, CsTaskTemplate $template): bool
    {
        return $this->create($user);
    }
}
