<?php

namespace App\Jobs;

use App\Models\Deployment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Throwable;

class DeployLatestRelease implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public function __construct(public Deployment $deployment)
    {
        $this->timeout = config('deploy.timeout') + 60;
    }

    /** Runs the exact release sequence documented in docs/DEPLOYMENT.md. */
    public function handle(): void
    {
        $base = base_path();
        $branch = config('deploy.branch');
        $stepTimeout = config('deploy.timeout');

        // Same lock file docker/entrypoint.sh uses for its first-boot
        // vendor/public-build install — holding it here for the whole
        // deploy stops a container restart's bootstrap install from
        // racing composer/npm against this job on the same bind-mounted
        // code.
        $lock = fopen(storage_path('.bootstrap.lock'), 'c');
        flock($lock, LOCK_EX);

        try {
            $this->deployment->update([
                'status' => Deployment::STATUS_RUNNING,
                'started_at' => now(),
                'commit_before' => trim(Process::path($base)->run(['git', 'rev-parse', 'HEAD'])->output()),
            ]);

            $steps = [
                ['git', 'fetch', 'origin', $branch],
                ['git', 'merge', '--ff-only', "origin/{$branch}"],
                ['composer', 'install', '--no-dev', '--classmap-authoritative', '--no-interaction'],
                ['npm', 'ci'],
                ['npm', 'run', 'build'],
                [PHP_BINARY, 'artisan', 'migrate', '--force'],
                [PHP_BINARY, 'artisan', 'optimize'],
                [PHP_BINARY, 'artisan', 'queue:restart'],
            ];

            foreach ($steps as $command) {
                $this->deployment->appendOutput('$ '.implode(' ', $command));

                $result = Process::path($base)->timeout($stepTimeout)->run($command);

                $this->deployment->appendOutput(trim($result->output().$result->errorOutput()));

                if ($result->failed()) {
                    $this->deployment->update(['status' => Deployment::STATUS_FAILED, 'finished_at' => now()]);

                    return;
                }
            }

            $this->deployment->update([
                'status' => Deployment::STATUS_SUCCEEDED,
                'commit_after' => trim(Process::path($base)->run(['git', 'rev-parse', 'HEAD'])->output()),
                'finished_at' => now(),
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->deployment->update(['status' => Deployment::STATUS_FAILED, 'finished_at' => now()]);
        $this->deployment->appendOutput('Deployment job failed: '.$exception->getMessage());
    }
}
