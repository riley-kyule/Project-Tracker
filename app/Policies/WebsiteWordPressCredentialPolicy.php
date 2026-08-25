<?php

namespace App\Policies;

use App\Models\User;

class WebsiteWordPressCredentialPolicy
{
    /** Covers create/update/delete/sync/test alike — no per-row ownership concept. */
    public function manage(User $user): bool
    {
        return $user->can('wordpress.manage');
    }
}
