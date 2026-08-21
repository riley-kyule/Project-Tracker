<?php

namespace Tests\Unit\Services\Analytics;

use App\Services\Analytics\Contracts\BigQueryRunner;
use App\Services\Analytics\GscReportQuery;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Regression lock for a real production bug: analytics_core.gsc_daily_site
 * (and its sibling tables) store search_type as 'WEB'/'IMAGE'/'VIDEO'/'NEWS'
 * — uppercase — but every query here used to filter on lowercase 'web'.
 * BigQuery string comparison is case-sensitive, so every GSC query silently
 * returned zero rows against real data despite passing the full test suite,
 * because the fakes used elsewhere (see MarketingStatisticsControllerTest)
 * match on SQL shape, not literal filter values. This test inspects the
 * actual SQL text instead, so it can't pass the same way that one did.
 */
class GscReportQueryTest extends TestCase
{
    private function capturingRunner(array &$captured): BigQueryRunner
    {
        return new class($captured) implements BigQueryRunner
        {
            public function __construct(private array &$captured) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function rows(string $sql, array $parameters = []): array
            {
                $this->captured[] = $sql;

                return [];
            }
        };
    }

    public function test_every_gsc_query_filters_on_uppercase_web()
    {
        $captured = [];
        $query = new GscReportQuery($this->capturingRunner($captured));
        $from = Carbon::parse('2026-08-01');
        $to = Carbon::parse('2026-08-21');

        $query->dailyRows('example.com', $from, $to);
        $query->queries('example.com', $from, $to);
        $query->pages('example.com', $from, $to);
        $query->countries('example.com', $from, $to);
        $query->devices('example.com', $from, $to);
        $query->freshness();
        $query->summaryByWebsite(['example.com'], $from, $to);

        $this->assertCount(7, $captured);

        foreach ($captured as $sql) {
            $this->assertStringContainsString("search_type = 'WEB'", $sql);
            $this->assertStringNotContainsString("search_type = 'web'", $sql);
        }
    }
}
