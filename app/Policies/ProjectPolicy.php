<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return true; // Projects are company-visible; sensitive tasks still enforce board visibility.
    }

    public function create(User $user): bool
    {
        return $user->can('projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->hasAnyRole(['Administrator', 'CEO'])) {
            return true;
        }

        return $user->can('projects.manage')
            && ($project->owner_id === $user->id || $project->department_id === $user->department_id);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole('Administrator');
    }
}
