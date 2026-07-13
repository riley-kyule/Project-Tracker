<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\BigQueryRunner;
use Illuminate\Support\Carbon;

/**
 * SPECULATIVE — no Ahrefs data exists anywhere in BigQuery yet (verified:
 * `analytics_core` has no ahrefs_* table/view as of this writing, see
 * ANALYTICS_BIGQUERY_FINDINGS.md). This targets a table named following the
 * same convention as the real `gsc_daily_site` table in the same dataset
 * (`analytics_core.ahrefs_daily_site`) as a reasonable guess for whenever
 * an Ahrefs pipeline is built — not a confirmed schema. Filtered by
 * `domain`, matching GSC and the WebsiteRegistryQuery source, on the
 * assumption a real pipeline would follow the same convention — adjust
 * once the real schema is known.
 *
 * Every method here will fail with a "table not found" error until that
 * pipeline exists; callers must treat Ahrefs as an optional source (see
 * MarketingStatisticsController's per-source try/catch) exactly like GA4
 * was treated while its own access gap was unresolved.
 */
class AhrefsReportQuery
{
    public function __construct(private BigQueryRunner $runner) {}

    public function isConfigured(): bool
    {
        return $this->runner->isConfigured();
    }

    /**
     * @return array<int, array{data_date: string, domain_rating: float|null, backlinks: int, referring_domains: int, organic_keywords: int, estimated_traffic: int, new_backlinks: int, lost_backlinks: int, keyword_gains: int, keyword_losses: int}>
     */
    public function dailyRows(string|array|null $domain, Carbon $from, Carbon $to): array
    {
        [$clause, $params] = $this->optionalDomainClause($domain);

        return $this->runner->rows(<<<SQL
            SELECT
              data_date,
              AVG(domain_rating) AS domain_rating,
              SUM(backlinks) AS backlinks,
              SUM(referring_domains) AS referring_domains,
              SUM(organic_keywords) AS organic_keywords,
              SUM(estimated_traffic) AS estimated_traffic,
              SUM(new_backlinks) AS new_backlinks,
              SUM(lost_backlinks) AS lost_backlinks,
              SUM(keyword_gains) AS keyword_gains,
              SUM(keyword_losses) AS keyword_losses
            FROM `analytics_core.ahrefs_daily_site`
            WHERE data_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY data_date
            ORDER BY data_date
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
    }

    public function freshness(): array
    {
        return $this->runner->rows(<<<'SQL'
            SELECT domain, MAX(data_date) AS latest_date, DATE_DIFF(CURRENT_DATE(), MAX(data_date), DAY) AS days_behind
            FROM `analytics_core.ahrefs_daily_site`
            GROUP BY domain
            SQL);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function optionalDomainClause(string|array|null $domain): array
    {
        if ($domain === null) {
            return ['', []];
        }

        if (is_array($domain)) {
            return [' AND domain IN UNNEST(@domains)', ['domains' => array_values($domain)]];
        }

        return [' AND domain = @domain', ['domain' => $domain]];
    }
}
