<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsReportBuilder;
use App\Services\Analytics\GscReportQuery;
use App\Services\Analytics\MarketingStatisticsFilters;
use App\Services\Analytics\TrafficDashboardQuery;
use App\Services\Analytics\WebsiteRegistryQuery;
use Illuminate\Console\Command;

/**
 * Pre-populates the Marketing Statistics GA4/GSC cache (see AnalyticsCache)
 * for its default views, so the first person to open Marketing Statistics
 * or the CEO dashboard's traffic widget each day gets a cached response
 * instead of paying for a live BigQuery round-trip. Scheduled shortly after
 * midnight (routes/console.php).
 *
 * The CEO dashboard's traffic widget reads through the exact same
 * AnalyticsReportBuilder methods and cache keys as Marketing Statistics
 * (see TrafficDataController) — warming Marketing Statistics' "All Sites"
 * default here already warms the CEO widget's default view for free; the
 * only things unique to the widget are its breakdowns and per-site
 * comparison, warmed separately below.
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
        WebsiteRegistryQuery $registryQuery, TrafficDashboardQuery $ga4, GscReportQuery $gsc, AnalyticsReportBuilder $reportBuilder,
    ): int {
        $registry = $reportBuilder->registry($registryQuery);

        if ($registry === []) {
            $this->warn('Website registry unavailable or empty — skipping analytics cache warm.');

            return self::SUCCESS;
        }

        $domains = array_column($registry, 'domain');
        $warmed = $this->warmReports($ga4, $gsc, $reportBuilder, $domains);
        $this->warmBreakdownsAndComparison($ga4, $gsc, $reportBuilder, $registry, $domains[0]);

        $this->info("Warmed the analytics cache for {$warmed} site(s), including the All Sites aggregate.");

        return self::SUCCESS;
    }

    /** @param  array<int, string>  $domains */
    private function warmReports(TrafficDashboardQuery $ga4, GscReportQuery $gsc, AnalyticsReportBuilder $reportBuilder, array $domains): int
    {
        [$dateFrom, $dateTo] = MarketingStatisticsFilters::resolveRange('last_30_days', null, null);

        // null first: "All Sites"/"All Platforms" is both modules' default landing view.
        $targets = [null, ...$domains];

        foreach ($targets as $domain) {
            $reportBuilder->ga4Report($ga4, $domain, $dateFrom, $dateTo);
            $reportBuilder->gscReport($gsc, $domain, $dateFrom, $dateTo);
        }

        return count($targets);
    }

    /**
     * Nine extra BigQuery queries per target (5 GA4 + 4 GSC breakdowns) —
     * unlike warmReports() above, only worth paying for on the default
     * target ("All Platforms") plus the first individual website, not
     * every registered site. The comparison table is a fixed cost
     * regardless of site count, so it's always warmed once.
     */
    private function warmBreakdownsAndComparison(
        TrafficDashboardQuery $ga4, GscReportQuery $gsc, AnalyticsReportBuilder $reportBuilder, array $registry, string $firstDomain,
    ): void {
        [$dateFrom, $dateTo] = MarketingStatisticsFilters::resolveRange('last_30_days', null, null);

        foreach ([null, $firstDomain] as $domain) {
            $reportBuilder->ga4Breakdowns($ga4, $domain, $dateFrom, $dateTo);
            $reportBuilder->gscBreakdowns($gsc, $domain, $dateFrom, $dateTo);
        }

        $reportBuilder->websiteComparison($ga4, $gsc, $registry, $dateFrom, $dateTo);
    }
}
