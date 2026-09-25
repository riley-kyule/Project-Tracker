<?php

namespace App\Services\Cs;

use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves who the Customer Service Board's midnight report actually goes
 * to — Customer Service Board Requirements Specification v1.0 §11: the CEO,
 * the department's mapped HOD, and any configured additional recipients.
 * Configurable without a code change via the Customer Service Board
 * Settings page (App\Http\Controllers\Cs\CsNotificationSettingsController)
 * — nothing here is hardcoded. Reuses department_notification_recipients
 * directly, since that table isn't SEO-specific. Mirrors
 * App\Services\Seo\SeoNotificationRecipients.
 *
 * @return Collection<int, array{type: 'user'|'email', user: ?User, email: ?string, name: ?string}>
 */
class CsNotificationRecipients
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
        $add($department->assistantManager);

        foreach (DepartmentNotificationRecipient::query()->where('department_id', $department->id)->activeOn()->get() as $extra) {
            $add(null, $extra->email, $extra->label);
        }

        return $recipients;
    }
}
