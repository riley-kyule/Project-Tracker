<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanySettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        // Reuses departments.manage (via the `create` ability, since `update`
        // requires a Department instance) — these are the only org-wide
        // settings today (scheduling + SLA business hours), already
        // restricted to Admins/CEO.
        Gate::authorize('create', Department::class);

        $validated = $request->validate([
            'ceo_summary_time' => ['nullable', 'date_format:H:i'],
            'business_hours_start' => ['nullable', 'date_format:H:i'],
            'business_hours_end' => ['nullable', 'date_format:H:i'],
            'business_hours_days' => ['nullable', 'array'],
            'business_hours_days.*' => ['integer', 'between:1,7'],
        ]);

        $setting = CompanySetting::current();
        $old = $setting->only(array_keys($validated));
        $setting->update($validated);
        AuditLogger::log($setting, 'updated', $old, $setting->only(array_keys($validated)));

        return back()->with('success', 'Company settings updated.');
    }
}
