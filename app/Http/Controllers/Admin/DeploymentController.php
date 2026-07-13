<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeployLatestRelease;
use App\Models\Deployment;
use App\Services\AuditLogger;
use App\Services\GitStatusChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeploymentController extends Controller
{
    public function check(GitStatusChecker $checker): JsonResponse
    {
        Gate::authorize('viewAny', Deployment::class);

        return response()->json($checker->check());
    }

    public function latest(): JsonResponse
    {
        Gate::authorize('viewAny', Deployment::class);

        return response()->json([
            'deployment' => Deployment::query()->latest()->first(),
            'enabled' => (bool) config('deploy.enabled'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Deployment::class);

        if (! config('deploy.enabled')) {
            return response()->json(['message' => 'Self-deploy is disabled on this environment.'], 423);
        }

        if (Deployment::query()->whereIn('status', [Deployment::STATUS_PENDING, Deployment::STATUS_RUNNING])->exists()) {
            return response()->json(['message' => 'A deployment is already in progress.'], 409);
        }

        $deployment = Deployment::create([
            'actor_id' => $request->user()->id,
            'status' => Deployment::STATUS_PENDING,
        ]);

        AuditLogger::log($deployment, 'triggered');

        DeployLatestRelease::dispatch($deployment);

        return response()->json(['deployment' => $deployment], 201);
    }

    public function show(Deployment $deployment): JsonResponse
    {
        Gate::authorize('view', $deployment);

        return response()->json(['deployment' => $deployment->fresh()]);
    }
}
