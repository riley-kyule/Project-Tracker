<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Department;
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
            Board::VISIBILITY_DEPARTMENT => $this->hasDepartmentAccess($user, $board->department_id),
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
     * Admins and the CEO manage any board; department managers/assistants
     * manage boards belonging to their own department or any of its
     * sub-departments (e.g. Marketing's head sees/manages SEO's boards too).
     */
    public function manage(User $user, Board $board): bool
    {
        if ($user->hasAnyRole(['Administrator', 'CEO'])) {
            return true;
        }

        return $user->can('boards.manage') && $this->hasDepartmentAccess($user, $board->department_id);
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->hasRole('Administrator');
    }

    private function hasDepartmentAccess(User $user, ?int $departmentId): bool
    {
        if ($departmentId === null) {
            return false;
        }

        if ($user->department_id === $departmentId) {
            return true;
        }

        return Department::query()
            ->where('manager_id', $user->id)
            ->orWhere('assistant_manager_id', $user->id)
            ->get()
            ->contains(fn (Department $led) => in_array($departmentId, $led->descendantIds(), true));
    }
}
