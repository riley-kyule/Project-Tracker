<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.assets.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('hr.assets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.assets.manage');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can('hr.assets.manage');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('hr.assets.manage');
    }
}
