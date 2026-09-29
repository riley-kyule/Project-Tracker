<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsContinuityCheck;
use App\Models\CsPlatformAssignment;
use App\Models\Employee;
use App\Services\Cs\CsAccess;
use App\Services\Cs\CsContinuityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Customer Service Board Requirements Specification v1.0 §5.2 — an employee records their own daily continuity checks. */
class CsContinuityCheckController extends Controller
{
    public function store(Request $request, Employee $employee, CsContinuityService $service): RedirectResponse
    {
        abort_unless(CsAccess::owns($request->user(), $employee) && $request->user()->isCsEmployee(), 403, 'You can only log your own checks.');

        $validated = $request->validate([
            'platform_assignment_id' => ['nullable', 'integer', 'exists:cs_platform_assignments,id'],
            'check_type' => ['required', 'string', 'in:'.implode(',', CsContinuityCheck::TYPES)],
            'status' => ['nullable', 'string', 'in:ok,issue_found'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($validated['platform_assignment_id'])) {
            $owned = CsPlatformAssignment::query()->whereKey($validated['platform_assignment_id'])->where('employee_id', $employee->id)->exists();
            abort_unless($owned, 422, 'That platform assignment belongs to a different employee.');
        }

        try {
            $check = $service->recordCheck($employee, $validated, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['notes' => $e->getMessage()]);
        }

        return back()->with('success', "Check recorded (#{$check->id}).");
    }
}
