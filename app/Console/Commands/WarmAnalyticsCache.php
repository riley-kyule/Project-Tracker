<?php

namespace App\Console\Commands;

use App\Http\Controllers\TrafficDataController;
use App\Services\Analytics\AnalyticsCache;
use App\Services\Analytics\AnalyticsReportBuilder;
use App\Services\Analytics\GscReportQuery;
use App\Services\Analytics\MarketingStatisticsFilters;
use App\Services\Analytics\TrafficDashboardQuery;
use App\Services\Analytics\WebsiteRegistryQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Pre-populates the Marketing Statistics GA4/GSC cache and the CEO
 * dashboard's traffic-data cache (see AnalyticsCache) for their default
 * views, so the first person to open either each day gets a cached
 * response instead of paying for a live BigQuery round-trip. Scheduled
 * shortly after midnight (routes/console.php).
 *
 * The cache's own TTL (end of business day, company timezone) is computed
 * independently of this command — a missed or failed run just means the
 * first real viewer that day warms it live instead, not a broken page.
 */
class WarmAnalyticsCache extends Command
{
    protected $signature = 'ewms:warm-analytics-cache';

    protected $description = 'Pre-populate the Marketing Statistics and CEO dashboard GA4/GSC caches for their default views';

    public function handle(
        WebsiteRegistryQuery $registryQuery, TrafficDashboardQuery $ga4, GscReportQuery $gsc,
        AnalyticsReportBuilder $reportBuilder, AnalyticsCache $cache,
    ): int {
        $warmed = $this->warmMarketingStatistics($registryQuery, $ga4, $gsc, $reportBuilder, $cache);
        $this->warmCeoTrafficData($ga4, $cache);

        $this->info("Warmed the analytics cache for {$warmed} site(s), including the All Sites aggregate.");

        return self::SUCCESS;
    }

    private function warmMarketingStatistics(
        WebsiteRegistryQuery $registryQuery, TrafficDashboardQuery $ga4, GscReportQuery $gsc,
        AnalyticsReportBuilder $reportBuilder, AnalyticsCache $cache,
    ): int {
        // Routed through the same 'registry' cache key MarketingStatisticsController
        // reads, not a raw call — otherwise this warms GA4/GSC but the first
        // real page view of the day still pays for one live registry query.
        $registry = $reportBuilder->attempt(fn () => $cache->remember('registry', fn () => $registryQuery->websites()));

        if ($registry['status'] !== 'ok') {
            $this->warn("Website registry unavailable ({$registry['error']}) — skipping Marketing Statistics cache warm.");

            return 0;
        }

        [$dateFrom, $dateTo] = MarketingStatisticsFilters::resolveRange('last_30_days', null, null);
        $domains = array_column($registry['rows'], 'domain');

        // null first: "All Sites" is the module's default landing view.
        $targets = [null, ...$domains];

        foreach ($targets as $domain) {
            $reportBuilder->ga4Report($ga4, $domain, $dateFrom, $dateTo);
            $reportBuilder->gscReport($gsc, $domain, $dateFrom, $dateTo);
        }

        return count($targets);
    }

    /**
     * GA4's raw-event views (unlike GSC's materialized rollups) are slow
     * enough that even one uncached traffic-data payload can take minutes
     * (confirmed: over 3 minutes for a single website's 6 queries) — worth
     * warming despite the cost, but only for the picker's default ("All
     * Platforms", null domain) plus the first individual website, not all
     * ~70 registered sites the way Marketing Statistics warms above; a
     * CEO/Admin browsing other sites from the dropdown still pays the live
     * cost for those, same as any other cache miss.
     */
    private function warmCeoTrafficData(TrafficDashboardQuery $ga4, AnalyticsCache $cache): void
    {
        try {
            $websites = $cache->remember('ceo-traffic-websites', fn () => $ga4->mappedWebsites());
        } catch (Throwable $e) {
            $this->warn("CEO traffic website list unavailable ({$e->getMessage()}) — skipping CEO traffic cache warm.");

            return;
        }

        if ($websites === []) {
            return;
        }

        [$from, $to] = MarketingStatisticsFilters::resolveRange('last_30_days', null, null);
        [$compareFrom, $compareTo] = TrafficDataController::comparisonRange($from, $to, 'previous_period');

        // null first: "All Platforms" is the picker's default landing view,
        // same convention as Marketing Statistics' "All Sites".
        foreach ([null, $websites[0]['website_domain']] as $domain) {
            $this->warmCeoTrafficTarget($ga4, $cache, $domain, $from, $to, $compareFrom, $compareTo);
        }
    }

    private function warmCeoTrafficTarget(
        TrafficDashboardQuery $ga4, AnalyticsCache $cache, ?string $domain, Carbon $from, Carbon $to, Carbon $compareFrom, Carbon $compareTo,
    ): void {
        $key = AnalyticsCache::key('ceo-traffic', $domain, $from, $to, $compareFrom, $compareTo);

        try {
            // Shape must match TrafficDataController::index()'s cached
            // payload exactly (including 'configured') — this warms the
            // same cache key the controller reads, and whichever one writes
            // first is what every reader gets back verbatim.
            $cache->remember($key, fn () => [
                'configured' => true,
                'summary' => ['current' => $ga4->summary($domain, $from, $to), 'comparison' => $ga4->summary($domain, $compareFrom, $compareTo)],
                'trend' => $ga4->dailyTrend($domain, $from, $to),
                'trafficSources' => $ga4->trafficSources($domain, $from, $to),
                'devices' => $ga4->devices($domain, $from, $to),
                'landingPages' => $ga4->landingPages($domain, $from, $to),
                'locations' => $ga4->locations($domain, $from, $to),
            ]);
        } catch (Throwable $e) {
            $label = $domain ?? 'All Platforms';
            $this->warn("CEO traffic data warm failed for {$label} ({$e->getMessage()}).");
        }
    }
}
