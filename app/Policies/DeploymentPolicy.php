<?php

namespace App\Policies;

use App\Models\Deployment;
use App\Models\User;

class DeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('system.deploy');
    }

    public function view(User $user, Deployment $deployment): bool
    {
        return $user->can('system.deploy');
    }

    public function create(User $user): bool
    {
        return $user->can('system.deploy');
    }
}
