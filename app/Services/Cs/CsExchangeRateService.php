<?php

namespace App\Services\Cs;

use App\Models\CompanySetting;
use App\Models\CsExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Customer Service Board Requirements Specification v1.0 §4 "Currency": an
 * approved exchange-rate source and date behind every converted amount.
 * Pulls once a day from open.er-api.com (free, no API key, ~160 currencies
 * including KES) and stores the result so the board keeps its own dated
 * audit trail rather than depending on a live call at the moment of each
 * sale. A manually entered rate for the same date/currency is never
 * overwritten by the daily pull, so an HOD/finance override always wins.
 */
class CsExchangeRateService
{
    private const API_URL = 'https://open.er-api.com/v6/latest/';

    /** Fetches today's rates (base = the reporting currency) and stores one row per currency actually used by cs_sales_records, skipping any date/currency pair set manually. */
    public function syncToday(): int
    {
        $reportingCurrency = CompanySetting::current()->cs_reporting_currency ?? 'KES';
        $today = now()->toDateString();

        $rates = $this->fetch($reportingCurrency);

        $stored = 0;
        foreach ($rates as $currency => $rateFromReporting) {
            if ($currency === $reportingCurrency || (float) $rateFromReporting <= 0) {
                continue;
            }

            // open.er-api.com returns "1 reporting currency = X foreign currency";
            // cs_sales_records needs the inverse, "1 foreign currency = X reporting
            // currency", to convert a transaction amount into the reporting currency.
            $rateToReporting = round(1 / (float) $rateFromReporting, 6);

            $exists = CsExchangeRate::query()->where('rate_date', $today)->where('currency', $currency)->exists();
            if ($exists) {
                continue; // A manual entry (or an earlier run today) already covers this date/currency.
            }

            CsExchangeRate::query()->create([
                'rate_date' => $today,
                'currency' => $currency,
                'rate_to_reporting_currency' => $rateToReporting,
            ]);
            $stored++;
        }

        return $stored;
    }

    /** @return array<string, float> currency code => rate expressed as 1 reporting currency = X that currency */
    private function fetch(string $reportingCurrency): array
    {
        try {
            $response = Http::timeout(15)->retry(2, 1000)->get(self::API_URL.$reportingCurrency);
        } catch (Throwable $e) {
            Log::error('Exchange rate fetch failed.', ['error' => $e->getMessage()]);

            throw new RuntimeException('Could not reach the exchange rate provider.', previous: $e);
        }

        if (! $response->successful() || $response->json('result') !== 'success') {
            Log::error('Exchange rate provider returned an error.', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('The exchange rate provider returned an error.');
        }

        return $response->json('rates', []);
    }

    /** The rate to convert $currency into the reporting currency on $date — the most recent rate on or before $date, since a non-trading day (weekend, provider outage) leaves no row for that exact date. */
    public function rateOn(string $currency, string $date): ?CsExchangeRate
    {
        return CsExchangeRate::query()
            ->where('currency', $currency)
            ->where('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();
    }
}
