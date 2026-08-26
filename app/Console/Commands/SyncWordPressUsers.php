<?php

namespace App\Console\Commands;

use App\Jobs\SyncWordPressUsersForSite;
use App\Models\WordPressCredential;
use Illuminate\Console\Command;

class SyncWordPressUsers extends Command
{
    protected $signature = 'ewms:sync-wordpress-users {--site= : Only sync the credential for this WordPress site ID}';

    protected $description = 'Queue a WordPress user sync job for every connected site (or one, via --site).';

    public function handle(): int
    {
        $credentials = WordPressCredential::query()
            ->when($this->option('site'), fn ($query, $siteId) => $query->where('wordpress_site_id', $siteId))
            ->get();

        foreach ($credentials as $credential) {
            SyncWordPressUsersForSite::dispatch($credential->id);
        }

        $this->info("Queued sync for {$credentials->count()} site(s).");

        return self::SUCCESS;
    }
}
