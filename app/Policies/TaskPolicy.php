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
        return Gate::forUser($user)->allows('view', $task->board);
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
            || Gate::forUser($user)->allows('manage', $task->board);
    }

    public function move(User $user, Task $task): bool
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
}
