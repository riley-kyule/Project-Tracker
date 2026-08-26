<?php

namespace App\Jobs;

use App\Models\WordPressCredential;
use App\Services\WordPress\WordPressUserSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/** One job per site, so a hung or failing site never blocks or retries alongside the rest. */
class SyncWordPressUsersForSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $credentialId) {}

    public function handle(WordPressUserSync $sync): void
    {
        $credential = WordPressCredential::query()->find($this->credentialId);

        if (! $credential) {
            return; // Deleted between dispatch and run.
        }

        $sync->syncSite($credential);
    }

    public function failed(Throwable $exception): void
    {
        WordPressCredential::query()->whereKey($this->credentialId)->update([
            'status' => WordPressCredential::STATUS_ERROR,
            'last_error' => Str::limit($exception->getMessage(), 1000),
            'last_synced_at' => now(),
        ]);
    }
}
