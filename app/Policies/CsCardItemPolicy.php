<?php

namespace App\Policies;

use App\Models\CsCardItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Delegates entirely to the owning card's policy — an item is never authorized independently of its card. Mirrors SeoCardItemPolicy. */
class CsCardItemPolicy
{
    public function view(User $user, CsCardItem $item): bool
    {
        return Gate::forUser($user)->allows('view', $item->cardable);
    }

    public function update(User $user, CsCardItem $item): bool
    {
        return Gate::forUser($user)->allows('update', $item->cardable);
    }

    public function approve(User $user, CsCardItem $item): bool
    {
        return Gate::forUser($user)->allows('approve', $item->cardable);
    }
}
