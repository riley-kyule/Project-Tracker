<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Department;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Website::class);

        return Inertia::render('admin/websites/index', [
            'websites' => Website::query()
                ->with(['country:id,name', 'responsibleDepartment:id,name', 'responsibleUser:id,name'])
                ->orderBy('name')
                ->get(),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->where('status', User::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('create', Website::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Website::class);

        $validated = $this->validated($request);
        Website::create($validated);

        return back()->with('success', 'Website added.');
    }

    public function update(Request $request, Website $website): RedirectResponse
    {
        Gate::authorize('update', $website);

        $website->update($this->validated($request));

        return back()->with('success', 'Website updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'platform_type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'responsible_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'ga4_property_id' => ['nullable', 'string', 'max:100'],
            'gsc_property' => ['nullable', 'string', 'max:255'],
            'gsc_bigquery_dataset' => ['nullable', 'string', 'max:255'],
            'crm_platform_id' => ['nullable', 'string', 'max:100'],
            'ahrefs_target' => ['nullable', 'string', 'max:255'],
            'gtm_container_id' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
