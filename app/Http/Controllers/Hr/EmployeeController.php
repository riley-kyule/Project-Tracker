<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Http\Requests\Hr\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Employee::class);

        return Inertia::render('hr/employees/index', [
            'employees' => Employee::query()
                ->visibleTo(request()->user())
                ->with(['department:id,name', 'user:id,name,email'])
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'staff_number' => $e->staff_number,
                    'full_name' => $e->full_name,
                    'job_title' => $e->job_title,
                    'department' => $e->department?->only(['id', 'name']),
                    'employment_type' => $e->employment_type,
                    'employment_status' => $e->employment_status,
                    'contract_end_date' => $e->contract_end_date?->toDateString(),
                    'has_login' => $e->user_id !== null,
                ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'managers' => Employee::query()->active()->orderBy('first_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name]),
            'linkableUsers' => User::query()
                ->whereDoesntHave('employee')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'canManage' => request()->user()->can('hr.employees.manage'),
        ]);
    }

    public function show(Employee $employee): Response
    {
        Gate::authorize('view', $employee);

        $employee->load([
            'department:id,name',
            'manager:id,first_name,middle_name,last_name',
            'user:id,name,email',
            'nextOfKin',
            'contracts.department:id,name',
            'documents.uploader:id,name',
            'assetAssignments.asset:id,asset_tag,name',
            'recurringItems',
            'goals.cycle:id,name',
            'performanceReviews.cycle:id,name',
        ]);

        $canViewComp = request()->user()->can('viewCompensation', $employee);
        if ($canViewComp) {
            $employee->load('compensation');
        }

        return Inertia::render('hr/employees/show', [
            'employee' => [
                ...$employee->only([
                    'id', 'user_id', 'staff_number', 'first_name', 'middle_name', 'last_name',
                    'gender', 'marital_status', 'national_id_number', 'kra_pin',
                    'nssf_number', 'shif_number', 'insurance_membership_number', 'personal_email',
                    'phone', 'alt_phone', 'postal_address', 'physical_address', 'county',
                    'department_id', 'job_title', 'employment_type', 'manager_id', 'is_org_head',
                    'employment_status', 'termination_reason', 'rehire_eligible',
                    'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number',
                    'payment_method', 'mpesa_number', 'notes',
                ]),
                // Date-cast columns must go out as Y-m-d, not ISO datetime, or
                // the <input type="date"> fields on the edit form render blank.
                'date_of_birth' => $employee->date_of_birth?->toDateString(),
                'date_hired' => $employee->date_hired?->toDateString(),
                'contract_start_date' => $employee->contract_start_date?->toDateString(),
                'contract_end_date' => $employee->contract_end_date?->toDateString(),
                'probation_end_date' => $employee->probation_end_date?->toDateString(),
                'termination_date' => $employee->termination_date?->toDateString(),
                'full_name' => $employee->full_name,
                'tenure_months' => $employee->tenure_months,
                'department' => $employee->department?->only(['id', 'name']),
                'manager' => $employee->manager ? ['id' => $employee->manager->id, 'name' => $employee->manager->full_name] : null,
                'user' => $employee->user?->only(['id', 'name', 'email']),
                'next_of_kin' => $employee->nextOfKin,
                'contracts' => $employee->contracts->map(fn ($c) => [
                    ...$c->only(['id', 'title', 'employment_type', 'reason', 'notes']),
                    'start_date' => $c->start_date?->toDateString(),
                    'end_date' => $c->end_date?->toDateString(),
                    'department' => $c->department?->only(['id', 'name']),
                ]),
                'documents' => $employee->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->original_name,
                    'category' => $d->category,
                    'size_bytes' => $d->size_bytes,
                    'uploaded_by' => $d->uploader?->name,
                    'created_at' => $d->created_at,
                ]),
                'assets' => $employee->assetAssignments
                    ->sortByDesc('assigned_at')
                    ->values()
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'asset' => $a->asset?->only(['id', 'asset_tag', 'name']),
                        'assigned_at' => $a->assigned_at,
                        'returned_at' => $a->returned_at,
                        'expected_return_at' => $a->expected_return_at?->toDateString(),
                    ]),
                'recurring_items' => $employee->recurringItems->map(fn ($i) => [
                    ...$i->only(['id', 'kind', 'name', 'calc_type', 'is_taxable', 'is_pretax', 'affects_nssf', 'is_active']),
                    'amount' => (float) $i->amount,
                    'balance' => $i->balance !== null ? (float) $i->balance : null,
                    'starts_on' => $i->starts_on?->toDateString(),
                    'ends_on' => $i->ends_on?->toDateString(),
                ]),
                'goals' => $employee->goals->map(fn ($g) => [
                    ...$g->only(['id', 'title', 'description', 'weight', 'metric', 'progress_pct', 'status']),
                    'rating' => $g->rating !== null ? (float) $g->rating : null,
                    'due_on' => $g->due_on?->toDateString(),
                    'cycle' => $g->cycle?->name,
                ]),
                'reviews' => $employee->performanceReviews->map(fn ($r) => [
                    'id' => $r->id,
                    'cycle' => $r->cycle?->name,
                    'status' => $r->status,
                    'overall_rating' => $r->overall_rating !== null ? (float) $r->overall_rating : null,
                ]),
                'compensation' => $canViewComp
                    ? $employee->compensation->map(fn ($c) => [
                        'id' => $c->id,
                        'effective_from' => $c->effective_from->toDateString(),
                        'currency' => $c->currency,
                        'basic_salary' => (float) $c->basic_salary,
                        'allowances' => $c->allowances ?? [],
                        'change_reason' => $c->change_reason,
                    ])
                    : null,
            ],
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'managers' => Employee::query()->active()->where('id', '!=', $employee->id)->orderBy('first_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name]),
            // Login accounts that can be linked: any not already tied to another
            // employee, plus this employee's own current one.
            'linkableUsers' => User::query()
                ->where(fn ($q) => $q->whereDoesntHave('employee')->orWhere('id', $employee->user_id))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'canManage' => request()->user()->can('hr.employees.manage'),
            'canManageCompensation' => request()->user()->can('manageCompensation', $employee),
            'canViewCompensation' => $canViewComp,
            'canManageGoals' => request()->user()->can('hr.performance.manage')
                || $employee->manager?->user_id === request()->user()->id,
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $employee = Employee::create($this->normalize($request->validated()));

        AuditLogger::log($employee, 'created', [], ['name' => $employee->full_name, 'staff_number' => $employee->staff_number]);

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Employee record created.');
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $data = $this->normalize($request->validated());
        $old = $employee->only(array_keys($data));
        $employee->update($data);

        AuditLogger::log($employee, 'updated', $old, $employee->only(array_keys($data)));

        return back()->with('success', 'Employee record updated.');
    }

    /** The org head has no manager, whatever a stale form field might carry. */
    private function normalize(array $data): array
    {
        if (! empty($data['is_org_head'])) {
            $data['manager_id'] = null;
        }

        return $data;
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        Gate::authorize('delete', $employee);

        AuditLogger::log($employee, 'deleted', ['name' => $employee->full_name], []);
        $employee->delete();

        return redirect()->route('hr.employees.index')->with('success', 'Employee record removed.');
    }
}
