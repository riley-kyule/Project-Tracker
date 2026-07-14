<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\BigQueryRunner;
use Illuminate\Support\Carbon;

/**
 * Reads from the existing BigQuery `analytics_core` views (built outside
 * this codebase — see ANALYTICS_BIGQUERY_FINDINGS.md), never from raw GA4
 * events directly. `website_domain` (matching EWMS's own Postgres
 * `websites.domain` column) is the identifier used throughout, since the
 * views have no separate slug.
 *
 * These views are themselves live queries over raw events (not
 * materialized tables), so every method here stays date-bounded — an
 * unbounded query would rescan full history across every GA4 dataset.
 */
class TrafficDashboardQuery
{
    public function __construct(private BigQueryRunner $runner) {}

    public function isConfigured(): bool
    {
        return $this->runner->isConfigured();
    }

    /**
     * The site picker's option list, bounded to sites with activity in the
     * last 90 days (an unbounded DISTINCT here would rescan every GA4
     * dataset's full history — these views aren't materialized).
     *
     * @return array<int, array{website_domain: string, website_name: string, country: string|null}>
     */
    public function mappedWebsites(): array
    {
        return $this->runner->rows(<<<'SQL'
            SELECT DISTINCT website_domain, website_name, country
            FROM `analytics_core.vw_daily_website_metrics`
            WHERE event_date >= @since
            ORDER BY website_name
            SQL, ['since' => now()->subDays(90)->toDateString()]);
    }

    /** @return array{users: int, sessions: int, key_events: int, engagement_rate: float|null} */
    public function summary(string|array $websiteDomain, Carbon $from, Carbon $to): array
    {
        [$clause, $params] = $this->optionalWebsiteClause($websiteDomain);

        $metrics = $this->runner->rows(<<<SQL
            SELECT
              SUM(users) AS users,
              SUM(sessions) AS sessions,
              SAFE_DIVIDE(SUM(engaged_sessions), SUM(sessions)) AS engagement_rate
            FROM `analytics_core.vw_daily_website_metrics`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);

        $keyEvents = $this->runner->rows(<<<SQL
            SELECT SUM(key_event_count) AS key_events
            FROM `analytics_core.vw_key_events`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);

        return [
            'users' => $metrics[0]['users'] ?? 0,
            'sessions' => $metrics[0]['sessions'] ?? 0,
            'engagement_rate' => $metrics[0]['engagement_rate'] ?? null,
            'key_events' => $keyEvents[0]['key_events'] ?? 0,
        ];
    }

    /** @return array<int, array{event_date: string, users: int, sessions: int}> */
    public function dailyTrend(string|array $websiteDomain, Carbon $from, Carbon $to): array
    {
        [$clause, $params] = $this->optionalWebsiteClause($websiteDomain);

        return $this->runner->rows(<<<SQL
            SELECT event_date, SUM(users) AS users, SUM(sessions) AS sessions
            FROM `analytics_core.vw_daily_website_metrics`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY event_date
            ORDER BY event_date
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
    }

    /** @return array<int, array{source: string, medium: string, users: int}> */
    public function trafficSources(string|array|null $websiteDomain, Carbon $from, Carbon $to, int $limit = 8): array
    {
        [$clause, $params] = $this->optionalWebsiteClause($websiteDomain);

        return $this->runner->rows(<<<SQL
            SELECT source, medium, SUM(users) AS users
            FROM `analytics_core.vw_traffic_sources`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY source, medium
            ORDER BY users DESC
            LIMIT @row_limit
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'row_limit' => $limit]);
    }

    /** @return array<int, array{device_category: string, users: int}> */
    public function devices(string|array|null $websiteDomain, Carbon $from, Carbon $to): array
    {
        [$clause, $params] = $this->optionalWebsiteClause($websiteDomain);

        return $this->runner->rows(<<<SQL
            SELECT device_category, SUM(users) AS users
            FROM `analytics_core.vw_device_breakdown`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY device_category
            ORDER BY users DESC
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
    }

    /** @return array<int, array{page_location: string, users: int, page_views: int}> */
    public function landingPages(string|array|null $websiteDomain, Carbon $from, Carbon $to, int $limit = 10): array
    {
        [$clause, $params] = $this->optionalWebsiteClause($websiteDomain);

        return $this->runner->rows(<<<SQL
            SELECT page_location, SUM(users) AS users, SUM(page_views) AS page_views
            FROM `analytics_core.vw_landing_pages`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY page_location
            ORDER BY page_views DESC
            LIMIT @row_limit
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'row_limit' => $limit]);
    }

    /** @return array<int, array{user_country: string, users: int}> */
    public function locations(string|array|null $websiteDomain, Carbon $from, Carbon $to, int $limit = 10): array
    {
        [$clause, $params] = $this->optionalWebsiteClause($websiteDomain);

        return $this->runner->rows(<<<SQL
            SELECT user_country, SUM(users) AS users
            FROM `analytics_core.vw_geo_breakdown`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY user_country
            ORDER BY users DESC
            LIMIT @row_limit
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'row_limit' => $limit]);
    }

    /**
     * Per-day rows including engaged_sessions, for the Marketing Statistics
     * module's weighted engagement-rate rollups (see WeightedMetrics) —
     * distinct from dailyTrend() above (which the CEO dashboard widget
     * already uses and shouldn't be reshaped for it). A null
     * $websiteDomain aggregates across every mapped website ("All Sites").
     *
     * @return array<int, array{event_date: string, users: int, sessions: int, engaged_sessions: int}>
     */
    public function dailyRows(string|array|null $websiteDomain, Carbon $from, Carbon $to): array
    {
        [$clause, $params] = $this->optionalWebsiteClause($websiteDomain);

        return $this->runner->rows(<<<SQL
            SELECT event_date, SUM(users) AS users, SUM(sessions) AS sessions, SUM(engaged_sessions) AS engaged_sessions
            FROM `analytics_core.vw_daily_website_metrics`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY event_date
            ORDER BY event_date
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
    }

    public function keyEventsTotal(string|array|null $websiteDomain, Carbon $from, Carbon $to): int
    {
        [$clause, $params] = $this->optionalWebsiteClause($websiteDomain);

        $rows = $this->runner->rows(<<<SQL
            SELECT SUM(key_event_count) AS key_events
            FROM `analytics_core.vw_key_events`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);

        return (int) ($rows[0]['key_events'] ?? 0);
    }

    /** @return array<int, array{key_event: string, key_event_category: string, key_event_count: int, users: int}> */
    public function keyEventsBreakdown(string|array|null $websiteDomain, Carbon $from, Carbon $to): array
    {
        [$clause, $params] = $this->optionalWebsiteClause($websiteDomain);

        return $this->runner->rows(<<<SQL
            SELECT key_event, key_event_category, SUM(key_event_count) AS key_event_count, SUM(users) AS users
            FROM `analytics_core.vw_key_events`
            WHERE event_date BETWEEN @date_from AND @date_to{$clause}
            GROUP BY key_event, key_event_category
            ORDER BY key_event_count DESC
            SQL, [...$params, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);
    }

    /**
     * One grouped query across every requested domain, instead of calling
     * summary() once per website — used by the Website Comparison tab so
     * comparing N sites costs a fixed 2 queries, not 2N.
     *
     * @param  array<int, string>  $domains
     * @return array<string, array{users: int, sessions: int, engagement_rate: float|null}>
     */
    public function summaryByWebsite(array $domains, Carbon $from, Carbon $to): array
    {
        $rows = $this->runner->rows(<<<'SQL'
            SELECT
              website_domain,
              SUM(users) AS users,
              SUM(sessions) AS sessions,
              SAFE_DIVIDE(SUM(engaged_sessions), SUM(sessions)) AS engagement_rate
            FROM `analytics_core.vw_daily_website_metrics`
            WHERE website_domain IN UNNEST(@domains) AND event_date BETWEEN @date_from AND @date_to
            GROUP BY website_domain
            SQL, ['domains' => array_values($domains), 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);

        $byDomain = [];
        foreach ($rows as $row) {
            $byDomain[$row['website_domain']] = [
                'users' => (int) $row['users'],
                'sessions' => (int) $row['sessions'],
                'engagement_rate' => $row['engagement_rate'] !== null ? (float) $row['engagement_rate'] : null,
            ];
        }

        return $byDomain;
    }

    /**
     * Null aggregates across every site ("All Sites"); a string filters to
     * one; an array (2+ sites ticked for a scoped member report) filters to
     * that subset via IN UNNEST.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function optionalWebsiteClause(string|array|null $websiteDomain): array
    {
        if ($websiteDomain === null) {
            return ['', []];
        }

        if (is_array($websiteDomain)) {
            return [' AND website_domain IN UNNEST(@website_domains)', ['website_domains' => array_values($websiteDomain)]];
        }

        return [' AND website_domain = @website_domain', ['website_domain' => $websiteDomain]];
    }
}
