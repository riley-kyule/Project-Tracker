<?php

namespace Tests\Unit\Services\Analytics;

use App\Services\Analytics\WeightedMetrics;
use PHPUnit\Framework\TestCase;

/**
 * Extends the framework TestCase directly (not Tests\TestCase) — this is
 * pure PHP with no database, HTTP, or BigQuery dependency to bootstrap.
 */
class WeightedMetricsTest extends TestCase
{
    public function test_engagement_rate_is_weighted_by_sessions_not_averaged_per_row()
    {
        // A naive AVG of per-row rates would give (0.9 + 0.1) / 2 = 0.5.
        // Weighted by sessions, the high-volume low-engagement day dominates.
        $rows = [
            ['sessions' => 10, 'engaged_sessions' => 9],   // 90% engaged, low volume
            ['sessions' => 1000, 'engaged_sessions' => 100], // 10% engaged, high volume
        ];

        $rate = WeightedMetrics::engagementRate($rows);

        $this->assertEqualsWithDelta(109 / 1010, $rate, 0.0001);
        $this->assertLessThan(0.5, $rate);
    }

    public function test_engagement_rate_is_null_when_there_are_no_sessions()
    {
        $this->assertNull(WeightedMetrics::engagementRate([]));
        $this->assertNull(WeightedMetrics::engagementRate([['sessions' => 0, 'engaged_sessions' => 0]]));
    }

    public function test_ctr_is_weighted_by_impressions_not_averaged_per_row()
    {
        // Naive AVG of (50%, 1%) = 25.5%. Weighted by impressions, the huge
        // low-CTR row should pull the combined figure close to 1%.
        $rows = [
            ['clicks' => 5, 'impressions' => 10],       // 50% CTR, tiny volume
            ['clicks' => 100, 'impressions' => 100000], // 0.1% CTR, huge volume
        ];

        $ctr = WeightedMetrics::ctr($rows);

        $this->assertEqualsWithDelta(105 / 100010, $ctr, 0.00001);
        $this->assertLessThan(0.02, $ctr);
    }

    public function test_ctr_is_null_when_there_are_no_impressions()
    {
        $this->assertNull(WeightedMetrics::ctr([]));
        $this->assertNull(WeightedMetrics::ctr([['clicks' => 0, 'impressions' => 0]]));
    }

    public function test_average_position_is_weighted_by_impressions()
    {
        // Naive AVG of (1.0, 50.0) = 25.5. Weighted by impressions, the
        // high-volume position-50 row should dominate the combined figure.
        $rows = [
            ['impressions' => 10, 'average_position' => 1.0],
            ['impressions' => 990, 'average_position' => 50.0],
        ];

        $position = WeightedMetrics::averagePosition($rows);

        $expected = (10 * 1.0 + 990 * 50.0) / 1000;
        $this->assertEqualsWithDelta($expected, $position, 0.0001);
        $this->assertGreaterThan(40, $position);
    }

    public function test_average_position_is_null_when_there_are_no_impressions()
    {
        $this->assertNull(WeightedMetrics::averagePosition([]));
    }

    public function test_average_position_treats_missing_value_as_zero_not_a_skipped_row()
    {
        // A row with a null average_position still contributes its
        // impressions to the denominator — it must not be silently dropped.
        $rows = [
            ['impressions' => 100, 'average_position' => null],
            ['impressions' => 100, 'average_position' => 10.0],
        ];

        $this->assertEqualsWithDelta(5.0, WeightedMetrics::averagePosition($rows), 0.0001);
    }

    public function test_sum_adds_plain_values()
    {
        $this->assertSame(30, WeightedMetrics::sum([10, 20]));
        $this->assertSame(0, WeightedMetrics::sum([]));
    }
}
