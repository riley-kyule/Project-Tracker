<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsTaskTemplate;
use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Services\Cs\CsAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Customer Service Board Settings" — template library and notification
 * recipients as tabs on one page. Scoped to the Customer Service
 * department. Mirrors App\Http\Controllers\Seo\SeoSettingsController.
 */
class CsSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canTemplates = $user->can('viewAny', CsTaskTemplate::class);
        $canNotifications = $user->can('cs.settings.manage') && CsAccess::leadsBoard($user);
        abort_unless($canTemplates || $canNotifications, 403);

        $department = Department::query()->where('slug', 'customer-service')->firstOrFail();

        return Inertia::render('cs-board/settings/index', [
            'department' => $department,
            'templates' => $canTemplates
                ? CsTaskTemplate::query()->orderBy('card_type')->orderBy('section')->orderBy('position')->get()
                : [],
            'hod' => $canNotifications ? $department->resolveHod() : null,
            'recipients' => $canNotifications
                ? DepartmentNotificationRecipient::query()->where('department_id', $department->id)->orderByDesc('is_active')->orderBy('email')->get()
                : [],
            'can' => ['templates' => $canTemplates, 'notifications' => $canNotifications],
        ]);
    }
}
