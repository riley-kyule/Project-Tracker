<?php

namespace App\Jobs;

use App\Models\WebsiteWordPressCredential;
use App\Services\WordPress\WordPressUserSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/** One job per website, so a hung or failing site never blocks or retries alongside the other 130+. */
class SyncWordPressUsersForWebsite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $credentialId) {}

    public function handle(WordPressUserSync $sync): void
    {
        $credential = WebsiteWordPressCredential::query()->find($this->credentialId);

        if (! $credential) {
            return; // Deleted between dispatch and run.
        }

        $sync->syncWebsite($credential);
    }

    public function failed(Throwable $exception): void
    {
        WebsiteWordPressCredential::query()->whereKey($this->credentialId)->update([
            'status' => WebsiteWordPressCredential::STATUS_ERROR,
            'last_error' => Str::limit($exception->getMessage(), 1000),
            'last_synced_at' => now(),
        ]);
    }
}
