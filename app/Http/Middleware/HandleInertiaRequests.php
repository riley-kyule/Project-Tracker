<?php

namespace App\Http\Middleware;

use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user?->getAllPermissions()->pluck('name')->values() ?? [],
                'roles' => $user?->getRoleNames() ?? [],
                'managesDepartment' => $user
                    ? $user->hasRole('Department Manager')
                        || Department::query()->where('manager_id', $user->id)->orWhere('assistant_manager_id', $user->id)->exists()
                    : false,
                'hasWebsiteAssignments' => $user ? $user->websiteAssignments()->exists() : false,
                // Drives the "My HR" self-service nav entry.
                'hasEmployeeRecord' => $user ? $user->employee()->exists() : false,
                // Broader than the 'view marketing statistics' permission
                // alone — also true for Marketing department (and
                // sub-department) members, matching
                // User::canViewMarketingStatistics() exactly (the same
                // check MarketingStatisticsController enforces), so the nav
                // link never disagrees with what the page itself allows.
                'canViewMarketingStatistics' => $user?->canViewMarketingStatistics() ?? false,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // Per-row/per-site results from a WordPress bulk action (see
                // WordPressUserBulkActionController) — partial failure across many
                // external sites is the expected common case, so it's surfaced
                // alongside the summary flash string rather than collapsed into it.
                'bulkResults' => fn () => $request->session()->get('bulkResults'),
            ],
        ];
    }
}
