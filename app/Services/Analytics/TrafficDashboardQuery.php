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
    public function summary(string $websiteDomain, Carbon $from, Carbon $to): array
    {
        $metrics = $this->runner->rows(<<<'SQL'
            SELECT
              SUM(users) AS users,
              SUM(sessions) AS sessions,
              SAFE_DIVIDE(SUM(engaged_sessions), SUM(sessions)) AS engagement_rate
            FROM `analytics_core.vw_daily_website_metrics`
            WHERE website_domain = @website_domain AND event_date BETWEEN @date_from AND @date_to
            SQL, $this->dateParams($websiteDomain, $from, $to));

        $keyEvents = $this->runner->rows(<<<'SQL'
            SELECT SUM(key_event_count) AS key_events
            FROM `analytics_core.vw_key_events`
            WHERE website_domain = @website_domain AND event_date BETWEEN @date_from AND @date_to
            SQL, $this->dateParams($websiteDomain, $from, $to));

        return [
            'users' => $metrics[0]['users'] ?? 0,
            'sessions' => $metrics[0]['sessions'] ?? 0,
            'engagement_rate' => $metrics[0]['engagement_rate'] ?? null,
            'key_events' => $keyEvents[0]['key_events'] ?? 0,
        ];
    }

    /** @return array<int, array{event_date: string, users: int, sessions: int}> */
    public function dailyTrend(string $websiteDomain, Carbon $from, Carbon $to): array
    {
        return $this->runner->rows(<<<'SQL'
            SELECT event_date, SUM(users) AS users, SUM(sessions) AS sessions
            FROM `analytics_core.vw_daily_website_metrics`
            WHERE website_domain = @website_domain AND event_date BETWEEN @date_from AND @date_to
            GROUP BY event_date
            ORDER BY event_date
            SQL, $this->dateParams($websiteDomain, $from, $to));
    }

    /** @return array<int, array{source: string, medium: string, users: int}> */
    public function trafficSources(string $websiteDomain, Carbon $from, Carbon $to, int $limit = 8): array
    {
        return $this->runner->rows(<<<'SQL'
            SELECT source, medium, SUM(users) AS users
            FROM `analytics_core.vw_traffic_sources`
            WHERE website_domain = @website_domain AND event_date BETWEEN @date_from AND @date_to
            GROUP BY source, medium
            ORDER BY users DESC
            LIMIT @row_limit
            SQL, [...$this->dateParams($websiteDomain, $from, $to), 'row_limit' => $limit]);
    }

    /** @return array<int, array{device_category: string, users: int}> */
    public function devices(string $websiteDomain, Carbon $from, Carbon $to): array
    {
        return $this->runner->rows(<<<'SQL'
            SELECT device_category, SUM(users) AS users
            FROM `analytics_core.vw_device_breakdown`
            WHERE website_domain = @website_domain AND event_date BETWEEN @date_from AND @date_to
            GROUP BY device_category
            ORDER BY users DESC
            SQL, $this->dateParams($websiteDomain, $from, $to));
    }

    /** @return array<int, array{page_location: string, users: int, page_views: int}> */
    public function landingPages(string $websiteDomain, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->runner->rows(<<<'SQL'
            SELECT page_location, SUM(users) AS users, SUM(page_views) AS page_views
            FROM `analytics_core.vw_landing_pages`
            WHERE website_domain = @website_domain AND event_date BETWEEN @date_from AND @date_to
            GROUP BY page_location
            ORDER BY page_views DESC
            LIMIT @row_limit
            SQL, [...$this->dateParams($websiteDomain, $from, $to), 'row_limit' => $limit]);
    }

    /** @return array<int, array{user_country: string, users: int}> */
    public function locations(string $websiteDomain, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->runner->rows(<<<'SQL'
            SELECT user_country, SUM(users) AS users
            FROM `analytics_core.vw_geo_breakdown`
            WHERE website_domain = @website_domain AND event_date BETWEEN @date_from AND @date_to
            GROUP BY user_country
            ORDER BY users DESC
            LIMIT @row_limit
            SQL, [...$this->dateParams($websiteDomain, $from, $to), 'row_limit' => $limit]);
    }

    /** @return array<string, mixed> */
    private function dateParams(string $websiteDomain, Carbon $from, Carbon $to): array
    {
        return [
            'website_domain' => $websiteDomain,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ];
    }
}
