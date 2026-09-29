<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsContinuityIssue;
use App\Models\CsPlatformAssignment;
use App\Models\Employee;
use App\Services\Cs\CsAccess;
use App\Services\Cs\CsContinuityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Customer Service Board Requirements Specification v1.0 §5.2 "Issue
 * follow-through" — any team member can report an issue they find; closing
 * it (resolved / known exception / reassigned) is left open to whoever
 * actually fixed it, same as reporting, since continuity is a shared team
 * concern, not an HOD-only decision the way pay-affecting approvals are.
 */
class CsContinuityIssueController extends Controller
{
    public function store(Request $request, Employee $employee, CsContinuityService $service): RedirectResponse
    {
        abort_unless(CsAccess::owns($request->user(), $employee) && $request->user()->isCsEmployee(), 403, 'You can only report issues against your own record.');

        $validated = $request->validate([
            'platform_assignment_id' => ['nullable', 'integer', 'exists:cs_platform_assignments,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'owner' => ['required', 'string', 'max:255'],
            'severity' => ['required', 'string', 'in:'.implode(',', CsContinuityIssue::SEVERITIES)],
            'expected_resolution' => ['nullable', 'date'],
        ]);

        if (! empty($validated['platform_assignment_id'])) {
            $owned = CsPlatformAssignment::query()->whereKey($validated['platform_assignment_id'])->where('employee_id', $employee->id)->exists();
            abort_unless($owned, 422, 'That platform assignment belongs to a different employee.');
        }

        $issue = $service->reportIssue($employee, $validated, $request->user());

        return back()->with('success', "Issue reported (#{$issue->id}).");
    }

    public function close(Request $request, CsContinuityIssue $issue, CsContinuityService $service): RedirectResponse
    {
        $employee = $issue->employee;
        abort_unless($employee !== null && (CsAccess::owns($request->user(), $employee) || CsAccess::leadsEmployee($request->user(), $employee)), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:resolved,known_exception,reassigned'],
            'final_outcome' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $service->closeIssue($issue, $validated['status'], $validated['final_outcome'], $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Issue closed.');
    }

    public function reopen(Request $request, CsContinuityIssue $issue, CsContinuityService $service): RedirectResponse
    {
        $employee = $issue->employee;
        abort_unless($employee !== null && (CsAccess::owns($request->user(), $employee) || CsAccess::leadsEmployee($request->user(), $employee)), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $service->reopenIssue($issue, $validated['reason']);

        return back()->with('success', 'Issue reopened.');
    }
}
