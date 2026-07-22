<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        // Being added to a task directly (any assignment type) grants visibility
        // into that one task regardless of department/board access — the point
        // of adding someone from outside your department is that they couldn't
        // already see the board.
        $hasDirectAccess = Gate::forUser($user)->allows('view', $task->board)
            || $task->assignees()->where('user_id', $user->id)->exists();

        if (! $hasDirectAccess) {
            return false;
        }

        if (! $task->isConfidential()) {
            return true;
        }

        // Per PERMISSIONS_MATRIX.md "View confidential tasks": CEO/Administrator
        // are always authorized; a Department Manager needs an explicit grant;
        // everyone else (including IT Technician, Marketing, Customer Service,
        // Employee, Viewer) is denied regardless of board visibility.
        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        return $user->hasRole('Department Manager')
            && $task->confidentialGrants()->whereKey($user->id)->exists();
    }

    /** Per PERMISSIONS_MATRIX.md: only CEO/Administrator set confidentiality or manage its grant list. */
    public function manageConfidentiality(User $user, Task $task): bool
    {
        return $user->hasAnyRole(['CEO', 'Administrator']);
    }

    public function create(User $user, Board $board): bool
    {
        return $user->can('tasks.create') && Gate::forUser($user)->allows('view', $board);
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->hasAnyRole(['Administrator', 'CEO'])) {
            return true;
        }

        if (! $this->view($user, $task)) {
            return false;
        }

        return $task->created_by === $user->id
            || $task->primary_assignee_id === $user->id
            || Gate::forUser($user)->allows('manage', $task->board)
            || $task->assignees()->where('user_id', $user->id)->whereIn('assignment_type', ['assignee', 'collaborator'])->exists();
    }

    public function move(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /**
     * Per PERMISSIONS_MATRIX.md: CEO/Administrator override any task;
     * Department Manager and IT Technician override within their own department.
     */
    public function overrideDependency(User $user, Task $task): bool
    {
        if ($user->hasAnyRole(['Administrator', 'CEO'])) {
            return true;
        }

        return $user->hasAnyRole(['Department Manager', 'IT Technician'])
            && $task->department_id !== null
            && $user->department_id === $task->department_id;
    }

    /** Per WORKFLOWS.md: "Administrator or manager defines recurrence rule." */
    public function manageRecurrence(User $user, Task $task): bool
    {
        if ($user->hasAnyRole(['Administrator', 'CEO'])) {
            return true;
        }

        return $user->hasRole('Department Manager')
            && $task->department_id !== null
            && $user->department_id === $task->department_id;
    }

    /** Manual time-entry adjustments require the same manager scope as recurrence rules. */
    public function approveTimeEntry(User $user, Task $task): bool
    {
        return $this->manageRecurrence($user, $task);
    }

    /** The assigned reviewer decides; managers/admins can always step in. */
    public function reviewApproval(User $user, Task $task): bool
    {
        return $task->approver_id === $user->id || $this->manageRecurrence($user, $task);
    }
}
