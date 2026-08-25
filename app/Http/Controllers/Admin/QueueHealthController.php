<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** No Horizon dependency — reads the same failed_jobs/jobs tables Laravel's queue worker already writes to. */
class QueueHealthController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('system.deploy'), 403);

        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at'])
            ->map(fn ($job) => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'exception' => Str::limit($job->exception, 300),
                'failed_at' => $job->failed_at,
            ]);

        $pendingByQueue = DB::table('jobs')
            ->selectRaw('queue, count(*) as total, min(available_at) as oldest_available_at')
            ->groupBy('queue')
            ->get()
            ->map(fn ($row) => [
                'queue' => $row->queue,
                'total' => (int) $row->total,
                'oldest_available_at' => Carbon::createFromTimestamp($row->oldest_available_at)->toIso8601String(),
            ]);

        return Inertia::render('admin/queue-health', [
            'failedJobs' => $failedJobs,
            'failedJobsTotal' => DB::table('failed_jobs')->count(),
            'pendingByQueue' => $pendingByQueue,
        ]);
    }
}
