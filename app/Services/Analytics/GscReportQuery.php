<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\BigQueryRunner;
use Illuminate\Support\Carbon;

/**
 * Reads from the real, materialized `analytics_core.gsc_*` tables (built
 * outside this codebase — see ANALYTICS_BIGQUERY_FINDINGS.md). Unlike the
 * GA4 `vw_*` views, these are prepared daily rollups, not live queries over
 * raw export data. Filtered by `domain` — shared with GA4's registry (see
 * WebsiteRegistryQuery) — rather than these tables' own `website_id`
 * column, since GA4's `metadata.websites` registry has no such ID and
 * `domain` is the one field guaranteed to line up across both sources.
 *
 * `gsc_data_freshness` (a view in the same dataset) currently fails for
 * this project's service account because it joins a dataset
 * (`analytics_admin.gsc_property_registry`) we don't have access to —
 * freshness here is computed from `gsc_daily_site` directly instead.
 *
 * All queries default to search_type = 'web' — the primary "Search
 * results" surface GSC reports on by default; image/video/news are a
 * distinct dimension this module doesn't expose yet.
 */
class GscReportQuery
{
    public function __construct(private BigQueryRunner $runner) {}

    public function isConfigured(): bool
    {
        return $this->runner->isConfigured();
    }

    /**
     * Per-day rows for weighted CTR/average-position rollups (see
     * WeightedMetrics). Null $domain aggregates across every website.
     *
     * @return array<int, array{data_date: string, clicks: int, impressions: int, average_position: float|null}>
     */
    public function dailyRows(string|array|null $domain, Carbon $from, Carbon $to): array
    {
        [$clause, $params] = $this->optionalDomainClause($domain);

        return $this->runner->rows(<<<SQL
            SELECT
              data_date,
              SUM(clicks) AS clicks,
              SUM(impressions) AS impressions,
              SAFE_DIVIDE(SUM(average_position * impressions), SUM(impressions)) AS average_position
            FROM `analytics_core.gsc_daily_site`
            WHERE search_type = 'web' AND data_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY data_date
            ORDER BY data_date
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
    }

    /** @return array<int, array{query: string, clicks: int, impressions: int, ctr: float|null, average_position: float|null}> */
    public function queries(string|array|null $domain, Carbon $from, Carbon $to, int $limit = 10): array
    {
        [$clause, $params] = $this->optionalDomainClause($domain);

        return $this->runner->rows(<<<SQL
            SELECT
              query,
              SUM(clicks) AS clicks,
              SUM(impressions) AS impressions,
              SAFE_DIVIDE(SUM(clicks), SUM(impressions)) AS ctr,
              SAFE_DIVIDE(SUM(average_position * impressions), SUM(impressions)) AS average_position
            FROM `analytics_core.gsc_daily_queries`
            WHERE search_type = 'web' AND data_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY query
            ORDER BY clicks DESC
            LIMIT @row_limit
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'row_limit' => $limit]);
    }

    /** @return array<int, array{url: string, clicks: int, impressions: int, ctr: float|null}> */
    public function pages(string|array|null $domain, Carbon $from, Carbon $to, int $limit = 10): array
    {
        [$clause, $params] = $this->optionalDomainClause($domain);

        return $this->runner->rows(<<<SQL
            SELECT
              url,
              SUM(clicks) AS clicks,
              SUM(impressions) AS impressions,
              SAFE_DIVIDE(SUM(clicks), SUM(impressions)) AS ctr
            FROM `analytics_core.gsc_daily_pages`
            WHERE search_type = 'web' AND data_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY url
            ORDER BY clicks DESC
            LIMIT @row_limit
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'row_limit' => $limit]);
    }

    /** @return array<int, array{country: string, clicks: int, impressions: int}> */
    public function countries(string|array|null $domain, Carbon $from, Carbon $to, int $limit = 10): array
    {
        [$clause, $params] = $this->optionalDomainClause($domain);

        return $this->runner->rows(<<<SQL
            SELECT country, SUM(clicks) AS clicks, SUM(impressions) AS impressions
            FROM `analytics_core.gsc_daily_countries`
            WHERE search_type = 'web' AND data_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY country
            ORDER BY clicks DESC
            LIMIT @row_limit
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'row_limit' => $limit]);
    }

    /** @return array<int, array{device: string, clicks: int, impressions: int}> */
    public function devices(string|array|null $domain, Carbon $from, Carbon $to): array
    {
        [$clause, $params] = $this->optionalDomainClause($domain);

        return $this->runner->rows(<<<SQL
            SELECT device, SUM(clicks) AS clicks, SUM(impressions) AS impressions
            FROM `analytics_core.gsc_daily_devices`
            WHERE search_type = 'web' AND data_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY device
            ORDER BY clicks DESC
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
    }

    /**
     * Computed directly from gsc_daily_site rather than the blocked
     * gsc_data_freshness view (see class docblock).
     *
     * @return array<int, array{domain: string, latest_date: string|null, days_behind: int|null}>
     */
    public function freshness(): array
    {
        return $this->runner->rows(<<<'SQL'
            SELECT
              domain,
              MAX(data_date) AS latest_date,
              DATE_DIFF(CURRENT_DATE(), MAX(data_date), DAY) AS days_behind
            FROM `analytics_core.gsc_daily_site`
            WHERE search_type = 'web'
            GROUP BY domain
            SQL);
    }

    /**
     * One grouped query across every requested domain, instead of calling
     * dailyRows()/summing once per website — used by the Website Comparison
     * tab so comparing N sites costs a fixed 1 query, not N.
     *
     * @param  array<int, string>  $domains
     * @return array<string, array{clicks: int, impressions: int, average_position: float|null}>
     */
    public function summaryByWebsite(array $domains, Carbon $from, Carbon $to): array
    {
        $rows = $this->runner->rows(<<<'SQL'
            SELECT
              domain,
              SUM(clicks) AS clicks,
              SUM(impressions) AS impressions,
              SAFE_DIVIDE(SUM(average_position * impressions), SUM(impressions)) AS average_position
            FROM `analytics_core.gsc_daily_site`
            WHERE search_type = 'web' AND domain IN UNNEST(@domains) AND data_date BETWEEN @date_from AND @date_to
            GROUP BY domain
            SQL, ['domains' => array_values($domains), 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);

        $byDomain = [];
        foreach ($rows as $row) {
            $byDomain[$row['domain']] = [
                'clicks' => (int) $row['clicks'],
                'impressions' => (int) $row['impressions'],
                'average_position' => $row['average_position'] !== null ? (float) $row['average_position'] : null,
            ];
        }

        return $byDomain;
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
