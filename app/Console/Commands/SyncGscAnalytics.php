<?php

namespace App\Console\Commands;

use App\Models\AnalyticsSyncLog;
use App\Services\Analytics\GscSync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncGscAnalytics extends Command
{
    protected $signature = 'ewms:sync-gsc-analytics {date? : Y-m-d, defaults to today minus the configured sync lag}';

    protected $description = 'Pull Search Console Bulk Data Export data into website_gsc_daily_metrics for every website with a GSC property configured';

    public function handle(GscSync $sync): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : now()->subDays(config('analytics.gsc.sync_lag_days'));

        $logs = $sync->syncDate($date);

        $failed = $logs->where('status', AnalyticsSyncLog::STATUS_FAILED);

        foreach ($failed as $log) {
            $this->error("Website #{$log->website_id}: {$log->error_message}");
        }

        $this->info("Synced {$logs->count()} website(s) for {$date->toDateString()}: ".
            "{$logs->where('status', AnalyticsSyncLog::STATUS_SUCCESS)->count()} succeeded, {$failed->count()} failed.");

        return self::SUCCESS;
    }
}
