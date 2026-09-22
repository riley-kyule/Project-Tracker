<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ResetAllWordPressStaffPasswords;
use App\Models\WordPressPasswordReset;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * JSON + polling, same shape as DeploymentController — a bulk password reset
 * across every connected site is a long-running background job, not
 * something a single request/redirect cycle can wait on.
 */
class WordPressPasswordResetController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        return response()->json(['reset' => WordPressPasswordReset::query()->latest()->first()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        if (WordPressPasswordReset::query()->whereIn('status', [WordPressPasswordReset::STATUS_PENDING, WordPressPasswordReset::STATUS_RUNNING])->exists()) {
            return response()->json(['message' => 'A password reset is already in progress.'], 409);
        }

        $reset = WordPressPasswordReset::create([
            'actor_id' => $request->user()->id,
            'status' => WordPressPasswordReset::STATUS_PENDING,
        ]);

        AuditLogger::log($reset, 'wp_bulk_password_reset_started');

        ResetAllWordPressStaffPasswords::dispatch($reset->id);

        return response()->json(['reset' => $reset], 201);
    }

    public function show(Request $request, WordPressPasswordReset $reset): JsonResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        return response()->json(['reset' => $reset->fresh()]);
    }

    /**
     * Reads (never deletes on read alone — see acknowledge()) the generated
     * passwords parked in cache by the job. Once the cache entry is gone
     * (acknowledged, or the 30-minute TTL passed), this always returns
     * `expired: true` — there is no way to recover them short of running the
     * reset again, by design.
     */
    public function results(Request $request, WordPressPasswordReset $reset): JsonResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);
        abort_unless($reset->status === WordPressPasswordReset::STATUS_SUCCEEDED, 409);

        $results = Cache::get($reset->resultsCacheKey());

        return response()->json(['results' => $results, 'expired' => $results === null]);
    }

    /** Called once the admin has copied everything off the results panel, so the plaintext doesn't linger for the full TTL. */
    public function acknowledge(Request $request, WordPressPasswordReset $reset): JsonResponse
    {
        abort_unless($request->user()->can('wordpress.manage'), 403);

        Cache::forget($reset->resultsCacheKey());

        return response()->json(['status' => 'ok']);
    }
}
