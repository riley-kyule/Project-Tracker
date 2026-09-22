<?php

namespace App\Policies;

use App\Models\SeoCardItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Delegates entirely to the owning card's policy (SeoDailyCardPolicy/SeoWeeklyCardPolicy) — an item is never authorized independently of its card. */
class SeoCardItemPolicy
{
    public function view(User $user, SeoCardItem $item): bool
    {
        return Gate::forUser($user)->allows('view', $item->cardable);
    }

    public function update(User $user, SeoCardItem $item): bool
    {
        return Gate::forUser($user)->allows('update', $item->cardable);
    }

    public function approve(User $user, SeoCardItem $item): bool
    {
        return Gate::forUser($user)->allows('approve', $item->cardable);
    }
}
