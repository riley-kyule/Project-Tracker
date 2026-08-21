<?php

namespace App\Services\Analytics;

use App\Models\CompanySetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Caches BigQuery-backed Marketing Statistics results until the end of the
 * current business day (company timezone, same source as
 * DailySummarySchedule::timezone()) — GA4/GSC data doesn't change
 * meaningfully within a day, so re-querying BigQuery on every page view was
 * pure latency with no freshness benefit.
 *
 * Every call site wraps only the raw query, inside its own existing
 * try/catch — never the whole "already caught and status-wrapped" result —
 * so a transient BigQuery failure is never cached and stuck for the rest of
 * the day; only genuine successes (including a real empty result) are ever
 * stored. See AnalyticsReportBuilder for the pattern.
 */
class AnalyticsCache
{
    private const PREFIX = 'analytics';

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function remember(string $key, callable $callback, bool $forceRefresh = false): mixed
    {
        $cacheKey = self::PREFIX.':'.$key;

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $this->secondsUntilEndOfDay(), $callback);
    }

    /** Stable, order-independent key for a (source, domain(s), date range, comparison range) combination. */
    public static function key(
        string $source, string|array|null $domain, Carbon $from, Carbon $to, ?Carbon $compareFrom = null, ?Carbon $compareTo = null,
    ): string {
        $domainPart = match (true) {
            $domain === null => 'all',
            is_array($domain) => implode('+', collect($domain)->sort()->values()->all()),
            default => $domain,
        };

        $parts = [$source, $domainPart, $from->toDateString(), $to->toDateString()];

        if ($compareFrom !== null && $compareTo !== null) {
            $parts[] = $compareFrom->toDateString();
            $parts[] = $compareTo->toDateString();
        }

        return implode(':', $parts);
    }

    private function secondsUntilEndOfDay(): int
    {
        $timezone = CompanySetting::current()->timezone ?: 'Africa/Nairobi';
        $now = Carbon::now($timezone);

        // A floor, not just defensive: remember() with a <=0 TTL means
        // "don't cache at all" to some stores, which would silently defeat
        // the whole point if this ever ran in the last minute of the day.
        return max(60, (int) $now->diffInSeconds($now->copy()->endOfDay()));
    }
}
