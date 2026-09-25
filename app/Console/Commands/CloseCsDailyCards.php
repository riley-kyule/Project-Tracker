<?php

namespace App\Console\Commands;

use App\Services\Cs\CsCardLifecycleService;
use Illuminate\Console\Command;

/**
 * Polls (rather than fires exactly at midnight) so nothing depends on cron
 * granularity — Customer Service Board Requirements Specification v1.0
 * §5.3. See CsCardLifecycleService::closeDueCards() for the actual
 * close/snapshot/recreate logic. Mirrors App\Console\Commands\CloseSeoDailyCards.
 */
class CloseCsDailyCards extends Command
{
    protected $signature = 'ewms:close-cs-daily-cards';

    protected $description = 'Close every Customer Service daily card whose work date has passed in the company timezone, snapshot it, open the next applicable card, and provision today\'s card for anyone who doesn\'t have one yet';

    public function handle(CsCardLifecycleService $lifecycle): int
    {
        $closed = $lifecycle->closeDueCards();
        $provisioned = $lifecycle->ensureTodaysCardsExist();

        $this->info("Closed {$closed} Customer Service daily card(s), provisioned {$provisioned} for today.");

        return self::SUCCESS;
    }
}
