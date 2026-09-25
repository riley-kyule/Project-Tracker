<?php

namespace App\Policies;

use App\Models\CsDailyCard;
use App\Models\User;
use App\Services\Cs\CsAccess;

/**
 * Customer Service Board Requirements Specification v1.0 §10 role table.
 * A member sees and edits only their own card; the department's manager,
 * assistant manager, the CEO and Administrators see and decide everyone's
 * (CsAccess mirrors how the Kanban tab decides leadership). The cs.cards.*
 * permissions are a coarse role gate only, never sufficient on their own.
 */
class CsDailyCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cs.cards.view') || CsAccess::leadsBoard($user);
    }

    public function view(User $user, CsDailyCard $card): bool
    {
        return ($card->employee !== null && CsAccess::owns($user, $card->employee))
            || CsAccess::leads($user, $card->department);
    }

    /** Employee-side edits (status/comments/evidence) on their own, still-open card only. */
    public function update(User $user, CsDailyCard $card): bool
    {
        return $user->can('cs.cards.update')
            && $card->employee !== null
            && CsAccess::owns($user, $card->employee)
            && $user->isCsEmployee()
            && $card->status !== CsDailyCard::STATUS_CLOSED;
    }

    public function approve(User $user, CsDailyCard $card): bool
    {
        return CsAccess::leads($user, $card->department);
    }

    public function reopen(User $user, CsDailyCard $card): bool
    {
        return CsAccess::leads($user, $card->department);
    }
}
