<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWordPressUsersForWebsite;
use App\Models\Website;
use App\Models\WebsiteWordPressCredential;
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
            'website_id' => ['nullable', 'integer'],
            'role' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $users = WordPressUser::query()
            ->with('website:id,name,domain')
            ->when($filters['website_id'] ?? null, fn ($query, $websiteId) => $query->where('website_id', $websiteId))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->whereJsonContains('roles', $role))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('website_id')
            ->orderBy('username')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/wordpress-users/index', [
            'users' => $users,
            'websites' => Website::query()
                ->whereHas('wordpressCredential')
                ->orderBy('name')
                ->get(['id', 'name', 'domain']),
            'roles' => WordPressUser::query()->pluck('roles')->flatten()->unique()->sort()->values(),
            'filters' => $filters,
        ]);
    }

    public function syncAll(): RedirectResponse
    {
        abort_unless(request()->user()->can('wordpress.manage'), 403);

        $credentials = WebsiteWordPressCredential::query()->get();

        foreach ($credentials as $credential) {
            SyncWordPressUsersForWebsite::dispatch($credential->id);
        }

        return back()->with('success', "Queued sync for {$credentials->count()} website(s).");
    }
}
