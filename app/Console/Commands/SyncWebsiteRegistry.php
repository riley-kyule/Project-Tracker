<?php

namespace App\Console\Commands;

use App\Services\Registry\WebsiteRegistrySync;
use Illuminate\Console\Command;

class SyncWebsiteRegistry extends Command
{
    protected $signature = 'registry:sync-websites';

    protected $description = 'Sync the website registry (domain, name, country) from BigQuery into the local websites table';

    public function handle(WebsiteRegistrySync $sync): int
    {
        $result = $sync->sync();

        $this->info("Synced {$result['total']} websites ({$result['created']} created, {$result['updated']} updated).");

        return self::SUCCESS;
    }
}
