<?php

namespace App\Policies;

use App\Models\Label;
use App\Models\User;

class LabelPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function manage(User $user): bool
    {
        return $user->can('labels.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Label $label): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Label $label): bool
    {
        return $this->manage($user);
    }
}
