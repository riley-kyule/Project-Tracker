<?php

namespace App\Services\Seo;

use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves who the SEO Board's midnight report actually goes to — SEO Board
 * Requirements Specification v1.1 §4.2.1: the CEO, the department's mapped
 * HOD, and any configured additional recipients. Configurable without a code
 * change via the Department Notification Settings page
 * (App\Http\Controllers\Seo\SeoNotificationSettingsController) — nothing
 * here is hardcoded.
 *
 * @return Collection<int, array{type: 'user'|'email', user: ?User, email: ?string, name: ?string}>
 */
class SeoNotificationRecipients
{
    public function resolve(Department $department): Collection
    {
        $recipients = collect();
        $seenEmails = [];

        $add = function (?User $user = null, ?string $email = null, ?string $name = null) use (&$recipients, &$seenEmails) {
            $resolvedEmail = $user?->email ?? $email;
            if ($resolvedEmail === null || in_array(strtolower($resolvedEmail), $seenEmails, true)) {
                return;
            }
            $seenEmails[] = strtolower($resolvedEmail);
            $recipients->push(['type' => $user !== null ? 'user' : 'email', 'user' => $user, 'email' => $email, 'name' => $name]);
        };

        foreach (User::role('CEO')->get() as $ceo) {
            $add($ceo);
        }

        $add($department->resolveHod());

        foreach (DepartmentNotificationRecipient::query()->where('department_id', $department->id)->activeOn()->get() as $extra) {
            $add(null, $extra->email, $extra->label);
        }

        return $recipients;
    }
}
