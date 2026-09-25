<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\CsPlatformAssignment;
use App\Models\Employee;
use App\Services\AuditLogger;
use App\Services\Cs\CsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Customer Service Board Requirements Specification v1.0 §5.2 — HOD-controlled platform/country assignment. */
class CsPlatformAssignmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', CsPlatformAssignment::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'country' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['required', 'date'],
            'backup_employee_id' => ['nullable', 'integer', 'exists:employees,id', 'different:employee_id'],
        ]);

        abort_if(empty($validated['website_id']) && empty($validated['country']), 422, 'Assign at least a platform or a country.');
        abort_unless(CsAccess::leadsEmployee($request->user(), Employee::query()->findOrFail($validated['employee_id'])), 403);

        $assignment = CsPlatformAssignment::query()->create([...$validated, 'is_active' => true, 'created_by' => $request->user()->id]);

        AuditLogger::log($assignment, 'cs_platform_assignment.created', [], $assignment->only(array_keys($validated)));

        return back()->with('success', 'Assignment saved.');
    }

    public function destroy(CsPlatformAssignment $assignment): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $old = $assignment->only(['is_active']);
        $assignment->update(['is_active' => false]);

        AuditLogger::log($assignment, 'cs_platform_assignment.deactivated', $old, ['is_active' => false]);

        return back()->with('success', 'Assignment removed.');
    }
}
