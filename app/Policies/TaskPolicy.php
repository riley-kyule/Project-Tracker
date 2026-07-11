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
}
