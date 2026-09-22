<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Models\SeoTaskTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "SEO Board Settings" — template library and notification recipients as
 * tabs on one page, replacing two separate pages. Scoped to the SEO
 * department itself; the spec covers no other department's board yet.
 */
class SeoSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canTemplates = $user->can('seo.templates.manage');
        $canNotifications = $user->can('seo.settings.manage');
        abort_unless($canTemplates || $canNotifications, 403);

        $department = Department::query()->where('slug', 'seo')->firstOrFail();

        return Inertia::render('seo-board/settings/index', [
            'department' => $department,
            'templates' => $canTemplates
                ? SeoTaskTemplate::query()->orderBy('card_type')->orderBy('section')->orderBy('position')->get()
                : [],
            'hod' => $canNotifications ? $department->resolveHod() : null,
            'recipients' => $canNotifications
                ? DepartmentNotificationRecipient::query()->where('department_id', $department->id)->orderByDesc('is_active')->orderBy('email')->get()
                : [],
            'can' => ['templates' => $canTemplates, 'notifications' => $canNotifications],
        ]);
    }
}
