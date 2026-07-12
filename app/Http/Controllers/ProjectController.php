<?php

namespace App\Http\Controllers;

use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Country;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('projects/index', [
            'projects' => Project::query()
                ->with(['department:id,name', 'owner:id,name'])
                ->withCount('tasks')
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'owners' => User::query()->where('status', User::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('projects.manage'),
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load(['department:id,name', 'owner:id,name', 'countries:id,name', 'websites:id,name', 'boards:id,name,project_id']);

        return Inertia::render('projects/show', [
            'project' => $project,
            'tasks' => $project->tasks()
                ->with(['board:id,name', 'assignee:id,name'])
                ->orderByRaw('completed_at is not null, due_at nulls last')
                ->limit(50)
                ->get(),
            'countries' => Country::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'websites' => Website::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('update', $project),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $project = DB::transaction(function () use ($request) {
            $project = Project::create($request->safe()->except(['country_ids', 'website_ids']));
            $project->countries()->sync($request->validated('country_ids', []));
            $project->websites()->sync($request->validated('website_ids', []));

            AuditLogger::log($project, 'created', [], ['name' => $project->name]);

            return $project;
        });

        return redirect()->route('projects.show', $project);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        DB::transaction(function () use ($request, $project) {
            $old = $project->only(['status', 'health_status', 'progress_percentage']);

            $project->update($request->safe()->except(['country_ids', 'website_ids']));

            if ($request->has('country_ids')) {
                $project->countries()->sync($request->validated('country_ids'));
            }
            if ($request->has('website_ids')) {
                $project->websites()->sync($request->validated('website_ids'));
            }

            AuditLogger::log($project, 'updated', $old, $project->only(['status', 'health_status', 'progress_percentage']));
        });

        return back();
    }
}
