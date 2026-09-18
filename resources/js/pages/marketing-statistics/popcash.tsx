import { CampaignPerformanceSection, type LocationRow } from '@/components/marketing-statistics/campaign-performance-section';
import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { buildFilterQuery, MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { TrendChart } from '@/components/marketing-statistics/trend-chart';
import { type Kpi, type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';

type PopcashTrendPoint = {
    data_date: string;
    money_spent: number;
    cpm: number | null;
    impressions: number;
};

function currency(value: number): string {
    return new Intl.NumberFormat('en', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(value);
}

export default function PopcashReport({
    selected,
    websites,
    source,
    kpis,
    trend,
    campaign_ga4,
    campaign_ga4_locations,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    source: SourceStatus;
    kpis: Record<string, Kpi> | null;
    trend: PopcashTrendPoint[];
    /** Popcash-attributed GA4 KPIs (source/medium filtered) — see CampaignPerformanceSection. */
    campaign_ga4?: Record<string, Kpi> | null;
    campaign_ga4_locations?: LocationRow[] | null;
}) {
    const query = buildFilterQuery(selected);

    return (
        <MarketingStatisticsShell active="popcash" selected={selected} websites={websites} sources={{ popcash: source }}>
            <CampaignPerformanceSection ga4={campaign_ga4} ga4Locations={campaign_ga4_locations} popcash={{ source, kpis }} query={query} />

            {kpis === null ? (
                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border border-dashed p-6 text-center">
                    {source.status === 'failed' ? (
                        <p className="text-muted-foreground text-sm" title={source.error ?? undefined}>
                            Popcash data couldn&apos;t be loaded right now. Try refreshing, or contact an administrator if this keeps happening.
                        </p>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            Popcash data isn&apos;t connected yet — contact an administrator to have it set up.
                        </p>
                    )}
                </div>
            ) : (
                <>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <KpiTile
                            label="Money spent"
                            kpi={kpis?.money_spent ?? null}
                            format={currency}
                            drilldownTitle="Money spent trend"
                            drilldown={<TrendChart data={trend} dateKey="data_date" series={[{ key: 'money_spent', name: 'Money spent' }]} valueFormat={currency} />}
                        />
                        <KpiTile
                            label="CPM"
                            kpi={kpis?.cpm ?? null}
                            format={currency}
                            drilldownTitle="CPM trend"
                            drilldown={<TrendChart data={trend} dateKey="data_date" series={[{ key: 'cpm', name: 'CPM' }]} valueFormat={currency} />}
                        />
                        <KpiTile
                            label="Impressions"
                            kpi={kpis?.impressions ?? null}
                            drilldownTitle="Impressions trend"
                            drilldown={<TrendChart data={trend} dateKey="data_date" series={[{ key: 'impressions', name: 'Impressions' }]} />}
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <h3 className="mb-3 text-sm font-semibold">Spend trend</h3>
                            <TrendChart data={trend} dateKey="data_date" series={[{ key: 'money_spent', name: 'Money spent' }]} valueFormat={currency} />
                        </div>
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <h3 className="mb-3 text-sm font-semibold">CPM &amp; impressions trend</h3>
                            <TrendChart
                                data={trend}
                                dateKey="data_date"
                                series={[
                                    { key: 'cpm', name: 'CPM' },
                                    { key: 'impressions', name: 'Impressions' },
                                ]}
                            />
                        </div>
                    </div>
                </>
            )}
        </MarketingStatisticsShell>
    );
}
