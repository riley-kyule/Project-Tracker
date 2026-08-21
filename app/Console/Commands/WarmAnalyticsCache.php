<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsCache;
use App\Services\Analytics\AnalyticsReportBuilder;
use App\Services\Analytics\GscReportQuery;
use App\Services\Analytics\MarketingStatisticsFilters;
use App\Services\Analytics\TrafficDashboardQuery;
use App\Services\Analytics\WebsiteRegistryQuery;
use Illuminate\Console\Command;

/**
 * Pre-populates the Marketing Statistics GA4/GSC cache (see AnalyticsCache)
 * for the default "last 30 days" range across every registered website —
 * plus the "All Sites" aggregate — so the first person to open the
 * dashboard each day gets a cached response instead of paying for a live
 * BigQuery round-trip. Scheduled shortly after midnight (routes/console.php).
 *
 * The cache's own TTL (end of business day, company timezone) is computed
 * independently of this command — a missed or failed run just means the
 * first real viewer that day warms it live instead, not a broken page.
 */
class WarmAnalyticsCache extends Command
{
    protected $signature = 'ewms:warm-analytics-cache';

    protected $description = 'Pre-populate the Marketing Statistics GA4/GSC cache for the default date range across all websites';

    public function handle(
        WebsiteRegistryQuery $registryQuery, TrafficDashboardQuery $ga4, GscReportQuery $gsc,
        AnalyticsReportBuilder $reportBuilder, AnalyticsCache $cache,
    ): int {
        // Routed through the same 'registry' cache key MarketingStatisticsController
        // reads, not a raw call — otherwise this warms GA4/GSC but the first
        // real page view of the day still pays for one live registry query.
        $registry = $reportBuilder->attempt(fn () => $cache->remember('registry', fn () => $registryQuery->websites()));

        if ($registry['status'] !== 'ok') {
            $this->warn("Website registry unavailable ({$registry['error']}) — skipping cache warm.");

            return self::SUCCESS;
        }

        [$dateFrom, $dateTo] = MarketingStatisticsFilters::resolveRange('last_30_days', null, null);
        $domains = array_column($registry['rows'], 'domain');

        // null first: "All Sites" is the module's default landing view.
        $targets = [null, ...$domains];
        $warmed = 0;

        foreach ($targets as $domain) {
            $reportBuilder->ga4Report($ga4, $domain, $dateFrom, $dateTo);
            $reportBuilder->gscReport($gsc, $domain, $dateFrom, $dateTo);
            $warmed++;
        }

        $this->info("Warmed the analytics cache for {$warmed} site(s), including the All Sites aggregate.");

        return self::SUCCESS;
    }
}
