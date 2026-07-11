<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Board $board): bool
    {
        if ($this->manage($user, $board)) {
            return true;
        }

        if (! $board->is_active) {
            return false;
        }

        return match ($board->visibility) {
            Board::VISIBILITY_COMPANY => true,
            Board::VISIBILITY_DEPARTMENT => $user->department_id === $board->department_id,
            Board::VISIBILITY_RESTRICTED => $board->members()->whereKey($user->id)->exists(),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->can('boards.manage');
    }

    public function update(User $user, Board $board): bool
    {
        return $this->manage($user, $board);
    }

    /**
     * Admins and the CEO manage any board; department managers manage
     * boards belonging to their own department.
     */
    public function manage(User $user, Board $board): bool
    {
        if ($user->hasAnyRole(['Administrator', 'CEO'])) {
            return true;
        }

        return $user->can('boards.manage')
            && $board->department_id !== null
            && ($user->department_id === $board->department_id
                || $board->department?->manager_id === $user->id);
    }
}
