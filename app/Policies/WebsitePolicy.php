<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('registry.manage');
    }

    public function update(User $user, Website $website): bool
    {
        return $user->can('registry.manage');
    }
}
