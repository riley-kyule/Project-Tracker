<?php

namespace App\Console\Commands;

use App\Jobs\SyncWordPressUsersForWebsite;
use App\Models\WebsiteWordPressCredential;
use Illuminate\Console\Command;

class SyncWordPressUsers extends Command
{
    protected $signature = 'ewms:sync-wordpress-users {--website= : Only sync the credential for this website ID}';

    protected $description = 'Queue a WordPress user sync job for every connected website (or one, via --website).';

    public function handle(): int
    {
        $credentials = WebsiteWordPressCredential::query()
            ->when($this->option('website'), fn ($query, $websiteId) => $query->where('website_id', $websiteId))
            ->get();

        foreach ($credentials as $credential) {
            SyncWordPressUsersForWebsite::dispatch($credential->id);
        }

        $this->info("Queued sync for {$credentials->count()} website(s).");

        return self::SUCCESS;
    }
}
