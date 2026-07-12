<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Department::class);

        return Inertia::render('admin/departments/index', [
            'departments' => Department::query()
                ->with('manager:id,name')
                ->withCount('users')
                ->orderBy('name')
                ->get(),
            'managers' => User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']),
            'canManage' => Gate::allows('create', Department::class),
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Department::class);

        $department = Department::create([
            ...$request->validated(),
            'slug' => $request->slug(),
        ]);
        AuditLogger::log($department, 'created', [], $department->only(['name', 'slug', 'manager_id', 'is_active']));

        return back()->with('success', 'Department created.');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);

        $old = $department->only(['name', 'slug', 'description', 'manager_id', 'is_active']);
        $department->update([
            ...$request->validated(),
            'slug' => $request->slug(),
        ]);
        AuditLogger::log($department, 'updated', $old, $department->only(array_keys($old)));

        return back()->with('success', 'Department updated.');
    }
}
