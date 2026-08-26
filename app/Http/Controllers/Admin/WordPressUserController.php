<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWordPressUsersForSite;
use App\Models\WordPressCredential;
use App\Models\WordPressSite;
use App\Models\WordPressUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WordPressUserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        $filters = $request->validate([
            'site_id' => ['nullable', 'integer'],
            'role' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $users = WordPressUser::query()
            ->with('site:id,name,domain')
            ->when($filters['site_id'] ?? null, fn ($query, $siteId) => $query->where('wordpress_site_id', $siteId))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->whereJsonContains('roles', $role))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('wordpress_site_id')
            ->orderBy('username')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/wordpress-users/index', [
            'users' => $users,
            'sites' => WordPressSite::query()
                ->with('credential')
                ->orderBy('name')
                ->get()
                ->map(fn (WordPressSite $site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'domain' => $site->domain,
                    'credential' => $site->credential ? [
                        'id' => $site->credential->id,
                        'wp_username' => $site->credential->wp_username,
                        'wp_app_password_set' => filled($site->credential->wp_app_password),
                        'status' => $site->credential->status,
                        'last_verified_at' => $site->credential->last_verified_at,
                        'last_synced_at' => $site->credential->last_synced_at,
                        'last_error' => $site->credential->last_error,
                    ] : null,
                ]),
            'roles' => WordPressUser::query()->pluck('roles')->flatten()->unique()->sort()->values(),
            'filters' => $filters,
        ]);
    }

    public function syncAll(): RedirectResponse
    {
        abort_unless(request()->user()->can('wordpress.manage'), 403);

        $credentials = WordPressCredential::query()->get();

        foreach ($credentials as $credential) {
            SyncWordPressUsersForSite::dispatch($credential->id);
        }

        return back()->with('success', "Queued sync for {$credentials->count()} site(s).");
    }
}
