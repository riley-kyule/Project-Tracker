<?php

namespace App\Console\Commands;

use App\Models\AnalyticsSyncLog;
use App\Services\Analytics\Ga4Sync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncGa4Analytics extends Command
{
    protected $signature = 'ewms:sync-ga4-analytics {date? : Y-m-d, defaults to yesterday}';

    protected $description = 'Pull GA4 BigQuery Export data into website_ga4_daily_metrics for every website with a GA4 property configured';

    public function handle(Ga4Sync $sync): int
    {
        $date = $this->argument('date') ? Carbon::parse($this->argument('date')) : now()->subDay();

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
