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

    /**
     * True if $user should see/manage a board under $departmentId, via any of
     * three independent routes: (1) membership — their primary department is
     * $departmentId or an ancestor of it, so belonging to "Marketing" gives
     * visibility into every sub-department's boards too, not just Marketing's
     * own; (2) granted membership — the same reach, but via an additional,
     * non-primary department_members grant (Admin > Departments); or (3)
     * leadership — they're the manager/assistant manager of a department
     * whose tree includes $departmentId, kept separate from (1)/(2) since a
     * lead's own department record doesn't have to match what they lead.
     */
    private function hasDepartmentAccess(User $user, ?int $departmentId): bool
    {
        if ($departmentId === null) {
            return false;
        }

        $memberDepartmentIds = [
            ...($user->department_id !== null ? [$user->department_id] : []),
            ...$user->departmentMemberships()->pluck('departments.id'),
        ];

        if (Department::query()
            ->whereIn('id', $memberDepartmentIds)
            ->get()
            ->contains(fn (Department $d) => in_array($departmentId, $d->descendantIds(), true))
        ) {
            return true;
        }

        return Department::query()
            ->where('manager_id', $user->id)
            ->orWhere('assistant_manager_id', $user->id)
            ->get()
            ->contains(fn (Department $led) => in_array($departmentId, $led->descendantIds(), true));
    }
}
