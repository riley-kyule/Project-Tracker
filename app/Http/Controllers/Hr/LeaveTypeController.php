<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreLeaveTypeRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        return Inertia::render('hr/leave/types', [
            'leaveTypes' => LeaveType::query()->withCount('requests')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreLeaveTypeRequest $request): RedirectResponse
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        LeaveType::create($request->validated());

        return back()->with('success', 'Leave type added.');
    }

    public function update(StoreLeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        // The code is a stable identifier (referenced by Leave settings and
        // reports) — it's locked in the UI and ignored here even if posted.
        $leaveType->update(collect($request->validated())->except('code')->all());

        return back()->with('success', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        Gate::authorize('adjustBalances', LeaveRequest::class);

        abort_if($leaveType->requests()->exists(), 422, 'This leave type is in use; deactivate it instead.');
        $leaveType->delete();

        return back()->with('success', 'Leave type removed.');
    }
}
