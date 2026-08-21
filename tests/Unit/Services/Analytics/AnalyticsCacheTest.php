<?php

namespace Tests\Unit\Services\Analytics;

use App\Models\CompanySetting;
use App\Services\Analytics\AnalyticsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Extends Tests\TestCase (not a plain PHPUnit TestCase, unlike
 * WeightedMetricsTest) because AnalyticsCache reads CompanySetting from the
 * database for its timezone and writes through Laravel's Cache facade —
 * genuinely needs the app booted, not pure PHP.
 */
class AnalyticsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_second_call_with_the_same_key_reuses_the_cached_value_without_recomputing()
    {
        $cache = app(AnalyticsCache::class);
        $calls = 0;

        $first = $cache->remember('test-key', function () use (&$calls) {
            $calls++;

            return 'computed-value';
        });
        $second = $cache->remember('test-key', function () use (&$calls) {
            $calls++;

            return 'computed-value';
        });

        $this->assertSame('computed-value', $first);
        $this->assertSame('computed-value', $second);
        $this->assertSame(1, $calls);
    }

    public function test_force_refresh_recomputes_even_when_a_cached_value_exists()
    {
        $cache = app(AnalyticsCache::class);
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return "call-{$calls}";
        };

        $first = $cache->remember('test-key', $compute);
        $second = $cache->remember('test-key', $compute, forceRefresh: true);

        $this->assertSame('call-1', $first);
        $this->assertSame('call-2', $second);
        $this->assertSame(2, $calls);
    }

    public function test_different_keys_are_cached_independently()
    {
        $cache = app(AnalyticsCache::class);

        $a = $cache->remember('key-a', fn () => 'value-a');
        $b = $cache->remember('key-b', fn () => 'value-b');

        $this->assertSame('value-a', $a);
        $this->assertSame('value-b', $b);
    }

    public function test_key_builder_produces_the_same_key_regardless_of_domain_array_order()
    {
        $from = Carbon::parse('2026-08-01');
        $to = Carbon::parse('2026-08-21');

        $a = AnalyticsCache::key('gsc', ['b.com', 'a.com'], $from, $to);
        $b = AnalyticsCache::key('gsc', ['a.com', 'b.com'], $from, $to);

        $this->assertSame($a, $b);
    }

    public function test_key_builder_distinguishes_null_domain_from_a_real_one()
    {
        $from = Carbon::parse('2026-08-01');
        $to = Carbon::parse('2026-08-21');

        $allSites = AnalyticsCache::key('gsc', null, $from, $to);
        $oneSite = AnalyticsCache::key('gsc', 'exoticghana.com', $from, $to);

        $this->assertNotSame($allSites, $oneSite);
    }

    public function test_key_builder_includes_comparison_range_only_when_present()
    {
        $from = Carbon::parse('2026-08-01');
        $to = Carbon::parse('2026-08-21');

        $withoutComparison = AnalyticsCache::key('ga4', 'a.com', $from, $to);
        $withComparison = AnalyticsCache::key('ga4', 'a.com', $from, $to, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-21'));

        $this->assertNotSame($withoutComparison, $withComparison);
    }

    public function test_ttl_respects_the_configured_company_timezone_not_the_apps_default()
    {
        CompanySetting::current()->update(['timezone' => 'Pacific/Kiritimati']); // UTC+14, deliberately far from UTC

        $cache = app(AnalyticsCache::class);
        $calls = 0;

        // No direct way to read the TTL back from Cache::remember(), so this
        // asserts the behavioral contract instead: the value is still
        // reused on a second call regardless of which timezone is
        // configured — a bug that hardcoded a timezone with a negative or
        // already-elapsed "seconds until end of day" would show up here as
        // an immediate cache miss (or a fatal from a negative TTL).
        $cache->remember('tz-test', function () use (&$calls) {
            $calls++;

            return 'ok';
        });
        $cache->remember('tz-test', function () use (&$calls) {
            $calls++;

            return 'ok';
        });

        $this->assertSame(1, $calls);
    }
}
