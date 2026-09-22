<?php

namespace App\Policies;

use App\Models\SeoDailyCard;
use App\Models\User;

/** SEO Board Requirements Specification v1.1 §9 role table. */
class SeoDailyCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('seo.cards.view');
    }

    public function view(User $user, SeoDailyCard $card): bool
    {
        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        if ($card->employee?->user_id === $user->id) {
            return true;
        }

        return $user->can('seo.cards.approve') && $card->department?->isLedBy($user);
    }

    /** Employee-side edits (status/comments/evidence) on their own, still-open card only. */
    public function update(User $user, SeoDailyCard $card): bool
    {
        return $user->can('seo.cards.update')
            && $card->employee?->user_id === $user->id
            && $card->status !== SeoDailyCard::STATUS_CLOSED;
    }

    public function approve(User $user, SeoDailyCard $card): bool
    {
        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        return $user->can('seo.cards.approve') && $card->department?->isLedBy($user);
    }

    public function reopen(User $user, SeoDailyCard $card): bool
    {
        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        return $user->can('seo.cards.reopen') && $card->department?->isLedBy($user);
    }
}
