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
        // requires a Department instance) — this is the only org-wide
        // scheduling setting today, and it's already restricted to Admins/CEO.
        Gate::authorize('create', Department::class);

        $validated = $request->validate(['ceo_summary_time' => ['nullable', 'date_format:H:i']]);

        $setting = CompanySetting::current();
        $old = $setting->only(['ceo_summary_time']);
        $setting->update($validated);
        AuditLogger::log($setting, 'updated', $old, $setting->only(['ceo_summary_time']));

        return back()->with('success', 'Company settings updated.');
    }
}
