<?php

namespace App\Console\Commands;

use App\Services\Cs\CsExchangeRateService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Customer Service Board Requirements Specification v1.0 §4 — daily pull of
 * the approved exchange-rate source. A failure here must never block sale
 * recording; App\Services\Cs\CsExchangeRateService::rateOn() simply falls
 * back to the most recent stored rate until this next succeeds.
 */
class SyncCsExchangeRates extends Command
{
    protected $signature = 'ewms:sync-cs-exchange-rates';

    protected $description = "Pull today's exchange rates for the Customer Service Board's revenue conversion";

    public function handle(CsExchangeRateService $service): int
    {
        try {
            $stored = $service->syncToday();
        } catch (Throwable $e) {
            $this->error("Exchange rate sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Stored {$stored} exchange rate(s).");

        return self::SUCCESS;
    }
}
