<?php

namespace App\Policies;

use App\Models\SeoTaskTemplate;
use App\Models\User;

/** The HOD template library — SEO Board Requirements Specification v1.1 §9 workflow rule 1. */
class SeoTaskTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('seo.cards.view') || $user->can('seo.templates.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('seo.templates.manage');
    }

    public function update(User $user, SeoTaskTemplate $template): bool
    {
        return $user->can('seo.templates.manage');
    }

    public function delete(User $user, SeoTaskTemplate $template): bool
    {
        return $this->update($user, $template);
    }
}
