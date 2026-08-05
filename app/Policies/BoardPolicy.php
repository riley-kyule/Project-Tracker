<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Department;
use App\Models\Task;
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

        // A task assignment (any type — see TaskAssigneeController) is itself
        // the mechanism that grants someone outside the board's department
        // access to it; TaskPolicy::view() already honors this per-task, but
        // the board page is an all-or-nothing gate in front of that, so it
        // needs the same exception or an assignee can never reach their own
        // task's card.
        if ($this->hasAssignedTask($user, $board)) {
            return true;
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

    private function hasAssignedTask(User $user, Board $board): bool
    {
        return Task::query()
            ->where('board_id', $board->id)
            ->whereHas('assignees', fn ($query) => $query->whereKey($user->id))
            ->exists();
    }

    /**
     * True if $user should see/manage a board under $departmentId — see
     * accessibleDepartmentIds() for the three routes this checks.
     */
    private function hasDepartmentAccess(User $user, ?int $departmentId): bool
    {
        if ($departmentId === null) {
            return false;
        }

        return in_array($departmentId, $this->accessibleDepartmentIds($user), true);
    }

    /**
     * Every department id $user has access to, via any of three independent
     * routes: (1) membership — their primary department, plus every
     * descendant of it, so belonging to "Marketing" gives access to every
     * sub-department's boards too, not just Marketing's own; (2) granted
     * membership — the same reach, but via an additional, non-primary
     * department_members grant (Admin > Departments); or (3) leadership —
     * they're the manager/assistant manager of a department, plus its
     * descendants, kept separate from (1)/(2) since a lead's own department
     * record doesn't have to match what they lead.
     *
     * Public (not just used internally by hasDepartmentAccess) so SQL-level
     * visibility scopes elsewhere — e.g. Task::scopeVisibleTo() — can reuse
     * this exact computation instead of re-deriving it or falling back to a
     * cruder "same department_id" filter that would miss the restricted-board
     * leak this method exists to close.
     *
     * @return array<int, int>
     */
    public function accessibleDepartmentIds(User $user): array
    {
        $memberDepartmentIds = [
            ...($user->department_id !== null ? [$user->department_id] : []),
            ...$user->departmentMemberships()->pluck('departments.id'),
        ];

        $ledDepartmentIds = Department::query()
            ->where('manager_id', $user->id)
            ->orWhere('assistant_manager_id', $user->id)
            ->pluck('id');

        $roots = Department::query()->whereIn('id', [...$memberDepartmentIds, ...$ledDepartmentIds])->get();

        $ids = [];

        foreach ($roots as $root) {
            $ids = [...$ids, ...$root->descendantIds()];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Same leadership route as (3) in accessibleDepartmentIds(), but
     * deliberately excludes plain membership (1)/(2): this answers "does
     * $user have manager-level authority here," not "can $user see this,"
     * so a rank-and-file department member doesn't count. Used by
     * TaskPolicy::view() / Task::scopeVisibleTo() to decide full,
     * not-just-my-own task visibility — see PERMISSIONS_MATRIX.md "View all
     * normal tasks": Department Manager gets the whole department,
     * everyone else only their own assigned/created/approver tasks.
     *
     * @return array<int, int>
     */
    public function managedDepartmentIds(User $user): array
    {
        $ledDepartmentIds = Department::query()
            ->where('manager_id', $user->id)
            ->orWhere('assistant_manager_id', $user->id)
            ->pluck('id');

        $roots = Department::query()->whereIn('id', $ledDepartmentIds)->get();

        $ids = [];

        foreach ($roots as $root) {
            $ids = [...$ids, ...$root->descendantIds()];
        }

        return array_values(array_unique($ids));
    }
}
