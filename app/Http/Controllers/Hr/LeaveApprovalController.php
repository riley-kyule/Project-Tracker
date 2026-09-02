<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\DecideLeaveRequestRequest;
use App\Models\LeaveRequest;
use App\Services\Hr\Leave\LeaveRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LeaveApprovalController extends Controller
{
    public function store(DecideLeaveRequestRequest $request, LeaveRequest $leaveRequest, LeaveRequestService $service): RedirectResponse
    {
        Gate::authorize('decide', $leaveRequest);

        $approve = $request->boolean('approve');

        $service->decide(
            $leaveRequest,
            $request->user(),
            $approve,
            $request->input('note'),
            $request->input('override_reason'),
        );

        return back()->with('success', $approve ? 'Leave approved.' : 'Leave rejected.');
    }
}
