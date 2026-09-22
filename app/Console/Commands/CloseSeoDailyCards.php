<?php

namespace App\Console\Commands;

use App\Services\Seo\SeoCardLifecycleService;
use Illuminate\Console\Command;

/**
 * Polls (rather than fires exactly at midnight) so nothing depends on cron
 * granularity or a browser being open — SEO Board Requirements Specification
 * v1.1 §4.2. See SeoCardLifecycleService::closeDueCards() for the actual
 * close/snapshot/recreate logic.
 */
class CloseSeoDailyCards extends Command
{
    protected $signature = 'ewms:close-seo-daily-cards';

    protected $description = 'Close every SEO daily card whose work date has passed in the company timezone, snapshot it, and open the next applicable card';

    public function handle(SeoCardLifecycleService $lifecycle): int
    {
        $closed = $lifecycle->closeDueCards();

        $this->info("Closed {$closed} SEO daily card(s).");

        return self::SUCCESS;
    }
}
