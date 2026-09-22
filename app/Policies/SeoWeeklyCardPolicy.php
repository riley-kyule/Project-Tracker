<?php

namespace App\Policies;

use App\Models\SeoWeeklyCard;
use App\Models\User;

/** SEO Board Requirements Specification v1.1 §9 role table — mirrors SeoDailyCardPolicy. */
class SeoWeeklyCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('seo.cards.view');
    }

    public function view(User $user, SeoWeeklyCard $card): bool
    {
        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        if ($card->employee?->user_id === $user->id) {
            return true;
        }

        return $user->can('seo.cards.approve') && $card->department?->isLedBy($user);
    }

    public function update(User $user, SeoWeeklyCard $card): bool
    {
        return $user->can('seo.cards.update')
            && $card->employee?->user_id === $user->id
            && $card->status !== SeoWeeklyCard::STATUS_CLOSED;
    }

    /** Approving the weekly plan before the week begins, or approving items during/after it — both HOD actions per §5/§9. */
    public function approve(User $user, SeoWeeklyCard $card): bool
    {
        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        return $user->can('seo.cards.approve') && $card->department?->isLedBy($user);
    }

    public function reopen(User $user, SeoWeeklyCard $card): bool
    {
        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        return $user->can('seo.cards.reopen') && $card->department?->isLedBy($user);
    }
}
