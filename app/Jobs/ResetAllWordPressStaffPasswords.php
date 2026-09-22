<?php

namespace App\Jobs;

use App\Models\WordPressPasswordReset;
use App\Models\WordPressUser;
use App\Services\WordPress\WordPressUserBulkAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Resets every synced WordPress staff account's password, across every
 * connected site, in one run — WordPressUserBulkAction::resetPassword()
 * already handles the per-site client/HTTP-failure isolation; this job's
 * only job is to drive it in site-sized batches (for progress reporting
 * the frontend can poll) and park the results somewhere the admin can
 * retrieve them exactly once.
 */
class ResetAllWordPressStaffPasswords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Generous on purpose: every user is a live HTTP round-trip to a WordPress
    // site (up to 30s each per WordPressUserClient), and this can span every
    // connected site's entire staff roster in one run. No user is blocked on
    // this — it's polled — so there's no cost to a wide ceiling.
    public int $timeout = 1800;

    // Not safely retryable: a retry would silently re-issue new passwords
    // for everyone who already succeeded on the first attempt, invalidating
    // ones an admin may have already copied and started handing out. If this
    // fails partway, the admin sees exactly that (status: failed, partial
    // counts) and can consciously choose to run it again.
    public int $tries = 1;

    public function __construct(public int $resetId) {}

    public function handle(WordPressUserBulkAction $bulkAction): void
    {
        $reset = WordPressPasswordReset::query()->find($this->resetId);

        if (! $reset) {
            return; // Deleted between dispatch and run.
        }

        $users = WordPressUser::query()->with('site:id,name')->get();

        $reset->update([
            'status' => WordPressPasswordReset::STATUS_RUNNING,
            'started_at' => now(),
            'total' => $users->count(),
        ]);

        if ($users->isEmpty()) {
            $reset->update(['status' => WordPressPasswordReset::STATUS_SUCCEEDED, 'finished_at' => now()]);

            return;
        }

        $allResults = [];
        $succeeded = 0;
        $failed = 0;

        // One batch per site (not per user) — enough granularity for a
        // meaningful progress bar without a DB write per individual reset.
        foreach ($users->groupBy('wordpress_site_id') as $siteUsers) {
            $results = $bulkAction->resetPassword($siteUsers);
            $allResults = [...$allResults, ...$results];

            foreach ($results as $result) {
                $result['status'] === 'ok' ? $succeeded++ : $failed++;
            }

            $reset->update([
                'processed' => count($allResults),
                'succeeded' => $succeeded,
                'failed' => $failed,
                'failures' => collect($allResults)
                    ->where('status', '!=', 'ok')
                    ->map(fn (array $r) => ['id' => $r['id'], 'username' => $r['username'] ?? null, 'site' => $r['site'] ?? null, 'error' => $r['error'] ?? null])
                    ->values()
                    ->all(),
            ]);
        }

        // The only place the generated passwords ever exist outside the
        // moment they were set — never written to wordpress_password_resets
        // or any other table. A short TTL is the backstop if nobody ever
        // retrieves/acknowledges them via the results endpoint.
        Cache::put($reset->resultsCacheKey(), $allResults, now()->addMinutes(30));

        $reset->update(['status' => WordPressPasswordReset::STATUS_SUCCEEDED, 'finished_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        WordPressPasswordReset::query()->whereKey($this->resetId)->update([
            'status' => WordPressPasswordReset::STATUS_FAILED,
            'finished_at' => now(),
        ]);
    }
}
