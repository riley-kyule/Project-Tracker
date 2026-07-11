<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use App\Models\User;
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

        Department::create([
            ...$request->validated(),
            'slug' => $request->slug(),
        ]);

        return back()->with('success', 'Department created.');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);

        $department->update([
            ...$request->validated(),
            'slug' => $request->slug(),
        ]);

        return back()->with('success', 'Department updated.');
    }
}
