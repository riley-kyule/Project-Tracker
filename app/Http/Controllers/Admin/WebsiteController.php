<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Department;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAssignment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Website::class);

        return Inertia::render('admin/websites/index', [
            'websites' => Website::query()
                ->with([
                    'country:id,name',
                    'responsibleDepartment:id,name',
                    'responsibleUser:id,name',
                    'assignedUsers:id,name',
                    'wordpressCredential',
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (Website $website) => [
                    ...$website->toArray(),
                    'wordpress_credential' => $website->wordpressCredential ? [
                        'id' => $website->wordpressCredential->id,
                        'wp_username' => $website->wordpressCredential->wp_username,
                        'wp_app_password_set' => filled($website->wordpressCredential->wp_app_password),
                        'status' => $website->wordpressCredential->status,
                        'last_verified_at' => $website->wordpressCredential->last_verified_at,
                        'last_synced_at' => $website->wordpressCredential->last_synced_at,
                        'last_error' => $website->wordpressCredential->last_error,
                    ] : null,
                ]),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->where('status', User::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name']),
            'teams' => WebsiteAssignment::TEAMS,
            'canManage' => Gate::allows('create', Website::class),
            'canManageWordPress' => $request->user()->can('wordpress.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Website::class);

        $validated = $this->validated($request);
        $website = Website::create(Arr::except($validated, ['wp_username', 'wp_app_password']));
        $this->syncWordPressCredential($request, $website, $validated);

        return back()->with('success', 'Website added.');
    }

    public function update(Request $request, Website $website): RedirectResponse
    {
        Gate::authorize('update', $website);

        $validated = $this->validated($request);
        $website->update(Arr::except($validated, ['wp_username', 'wp_app_password']));
        $this->syncWordPressCredential($request, $website, $validated);

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
            'crm_platform_id' => ['nullable', 'string', 'max:100'],
            'ahrefs_target' => ['nullable', 'string', 'max:255'],
            'gtm_container_id' => ['nullable', 'string', 'max:100'],
            'wp_username' => ['nullable', 'string', 'max:255'],
            'wp_app_password' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * WordPress credentials are edited from the same dialog as the rest of a
     * website's details, but they're a distinct, higher-risk, write-capable-
     * against-an-external-site capability — gated on wordpress.manage
     * independently of the registry.manage check above, same as the
     * standalone bulk user management page.
     */
    private function syncWordPressCredential(Request $request, Website $website, array $validated): void
    {
        if (blank($validated['wp_username'] ?? null) && blank($website->wordpressCredential)) {
            return;
        }

        abort_unless($request->user()->can('wordpress.manage'), 403);

        if (blank($validated['wp_username'] ?? null)) {
            return; // Removing credentials happens through the dedicated destroy action, not by blanking this field.
        }

        if (blank($website->domain)) {
            throw ValidationException::withMessages(['wp_username' => 'This website needs a domain before WordPress credentials can be added.']);
        }

        $credential = $website->wordpressCredential;
        $attrs = ['wp_username' => $validated['wp_username']];

        if (filled($validated['wp_app_password'] ?? null)) {
            $attrs['wp_app_password'] = $validated['wp_app_password'];
        } elseif (! $credential) {
            throw ValidationException::withMessages(['wp_app_password' => 'An Application Password is required.']);
        }

        if ($credential) {
            $old = $credential->only(['wp_username']);
            $credential->update($attrs);
            AuditLogger::log($credential, 'updated', $old, $credential->only(['wp_username']));
        } else {
            $credential = $website->wordpressCredential()->create($attrs);
            AuditLogger::log($credential, 'created', [], ['website_id' => $website->id, 'wp_username' => $credential->wp_username]);
        }
    }
}
