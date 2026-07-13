<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAssignment;
use App\Services\Analytics\AhrefsReportQuery;
use App\Services\Analytics\AnalyticsReportBuilder;
use App\Services\Analytics\GscReportQuery;
use App\Services\Analytics\TrafficDashboardQuery;
use App\Services\Crm\CrmReportQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Builds the "My Reports" payload for a single user: only the websites
 * *that user* is assigned to (never the full registry) are queried, split
 * by team — Marketing gets GA4/GSC/Ahrefs, Customer Service gets the CRM
 * scaffold — so an SEO assigned to two sites can tick just those two and
 * get a report scoped to exactly that subset.
 */
class WebsiteReportBuilder
{
    public function __construct(
        private TrafficDashboardQuery $ga4,
        private GscReportQuery $gsc,
        private AhrefsReportQuery $ahrefs,
        private CrmReportQuery $crm,
        private AnalyticsReportBuilder $reportBuilder,
    ) {}

    /** @param  array<int, int>  $websiteIds */
    public function build(User $user, array $websiteIds, Carbon $from, Carbon $to): array
    {
        $assignments = $user->websiteAssignments()
            ->whereIn('website_id', $websiteIds)
            ->with('website:id,name,domain')
            ->get();

        $marketingWebsites = $assignments->where('team', WebsiteAssignment::TEAM_MARKETING)
            ->pluck('website')->filter()->unique('id')->values();

        $csWebsites = $assignments->where('team', WebsiteAssignment::TEAM_CUSTOMER_SERVICE)
            ->pluck('website')->filter()->unique('id')->values();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'marketing' => $marketingWebsites->isEmpty() ? null : $this->buildMarketing($marketingWebsites, $from, $to),
            'customer_service' => $csWebsites->isEmpty() ? null : $this->buildCustomerService($csWebsites, $from, $to),
        ];
    }

    private function buildMarketing(Collection $websites, Carbon $from, Carbon $to): array
    {
        $domains = $websites->pluck('domain')->filter()->values()->all();
        $siteList = $this->siteList($websites);

        if ($domains === []) {
            return [
                'websites' => $siteList,
                'error' => 'No BigQuery domain mapped for the selected site(s).',
                'ga4' => null, 'gsc' => null, 'ahrefs' => null,
            ];
        }

        $ga4Report = $this->reportBuilder->ga4Report($this->ga4, $domains, $from, $to);
        $gscReport = $this->reportBuilder->gscReport($this->gsc, $domains, $from, $to);
        $ahrefsReport = $this->reportBuilder->ahrefsReport($this->ahrefs, $domains, $from, $to);

        return [
            'websites' => $siteList,
            'error' => null,
            'ga4' => $this->sourceResult($ga4Report),
            'gsc' => $this->sourceResult($gscReport),
            'ahrefs' => $this->sourceResult($ahrefsReport),
        ];
    }

    private function buildCustomerService(Collection $websites, Carbon $from, Carbon $to): array
    {
        $domains = $websites->pluck('domain')->filter()->values()->all();
        $siteList = $this->siteList($websites);

        try {
            $data = $this->crm->summary($domains, $from, $to);

            return ['websites' => $siteList, 'status' => 'ok', 'error' => null, 'data' => $data];
        } catch (Throwable $e) {
            return ['websites' => $siteList, 'status' => 'failed', 'error' => $e->getMessage(), 'data' => null];
        }
    }

    /** @return array<int, array{id: int, name: string, domain: string|null}> */
    private function siteList(Collection $websites): array
    {
        return $websites->map(fn (Website $website) => [
            'id' => $website->id,
            'name' => $website->name,
            'domain' => $website->domain,
        ])->values()->all();
    }

    private function sourceResult(array $report): array
    {
        return ['status' => $report['status'], 'error' => $report['error'], 'kpis' => $report['kpis']];
    }
}
