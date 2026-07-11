<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('admin/users/index', [
            'users' => User::query()
                ->with(['department:id,name', 'roles:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'department_id', 'manager_id', 'job_title', 'status', 'last_login_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department' => $user->department?->only(['id', 'name']),
                    'manager_id' => $user->manager_id,
                    'job_title' => $user->job_title,
                    'status' => $user->status,
                    'role' => $user->roles->first()?->name,
                    'last_login_at' => $user->last_login_at,
                ]),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'canManage' => request()->user()->can('users.manage'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        DB::transaction(function () use ($request, $user) {
            $user->update($request->safe()->except('role'));
            $user->syncRoles([$request->validated('role')]);
        });

        return back()->with('success', 'User updated.');
    }
}
