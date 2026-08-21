<?php

namespace App\Policies;

use App\Models\ChecklistTemplate;
use App\Models\User;

class ChecklistTemplatePolicy
{
    /** Global, like labels: every user who can create tasks should be able to browse and apply any template. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create');
    }

    public function update(User $user, ChecklistTemplate $template): bool
    {
        return $template->created_by === $user->id || $user->hasAnyRole(['Administrator', 'CEO']);
    }

    public function delete(User $user, ChecklistTemplate $template): bool
    {
        return $this->update($user, $template);
    }
}
