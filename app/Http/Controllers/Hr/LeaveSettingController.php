<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\UpdateLeaveSettingRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class LeaveSettingController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        return Inertia::render('hr/leave/settings', [
            'settings' => LeaveSetting::current(),
            'leaveTypeCodes' => LeaveType::query()->orderBy('name')->get(['code', 'name']),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(UpdateLeaveSettingRequest $request): RedirectResponse
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        $settings = LeaveSetting::current();
        $old = $settings->only(array_keys($request->validated()));
        $settings->update($request->validated());

        AuditLogger::log($settings, 'updated', $old, $settings->only(array_keys($request->validated())));

        return back()->with('success', 'Leave settings updated.');
    }
}
