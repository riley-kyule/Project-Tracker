import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { TrendChart } from '@/components/marketing-statistics/trend-chart';
import { type Kpi, type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';

type AhrefsTrendPoint = {
    data_date: string;
    domain_rating: number | null;
    backlinks: number;
    referring_domains: number;
    organic_keywords: number;
    estimated_traffic: number;
    new_backlinks: number;
    lost_backlinks: number;
    keyword_gains: number;
    keyword_losses: number;
};

export default function AhrefsReport({
    selected,
    websites,
    source,
    kpis,
    trend,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    source: SourceStatus;
    kpis: Record<string, Kpi> | null;
    trend: AhrefsTrendPoint[];
}) {
    const trendDrilldown = (key: keyof AhrefsTrendPoint, name: string) => <TrendChart data={trend} dateKey="data_date" series={[{ key, name }]} />;

    return (
        <MarketingStatisticsShell active="ahrefs" selected={selected} websites={websites} sources={{ ahrefs: source }}>
            {kpis === null ? (
                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border border-dashed p-6 text-center">
                    <p className="text-muted-foreground text-sm">
                        No Ahrefs data pipeline exists in BigQuery yet — this tab will populate automatically once one is built (see
                        ANALYTICS_BIGQUERY_FINDINGS.md).
                    </p>
                </div>
            ) : (
                <>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <KpiTile label="Domain Rating" kpi={kpis?.domain_rating ?? null} format={(v) => v.toFixed(1)} />
                        <KpiTile label="Backlinks" kpi={kpis?.backlinks ?? null} />
                        <KpiTile label="Referring domains" kpi={kpis?.referring_domains ?? null} />
                        <KpiTile
                            label="Organic keywords"
                            kpi={kpis?.organic_keywords ?? null}
                            drilldownTitle="Organic keywords trend"
                            drilldown={trendDrilldown('organic_keywords', 'Organic keywords')}
                        />
                        <KpiTile
                            label="Estimated organic traffic"
                            kpi={kpis?.estimated_organic_traffic ?? null}
                            drilldownTitle="Estimated organic traffic trend"
                            drilldown={trendDrilldown('estimated_traffic', 'Estimated traffic')}
                        />
                        <KpiTile
                            label="New backlinks"
                            kpi={kpis?.new_backlinks ?? null}
                            drilldownTitle="New backlinks trend"
                            drilldown={trendDrilldown('new_backlinks', 'New backlinks')}
                        />
                        <KpiTile
                            label="Lost backlinks"
                            kpi={kpis?.lost_backlinks ?? null}
                            drilldownTitle="Lost backlinks trend"
                            drilldown={trendDrilldown('lost_backlinks', 'Lost backlinks')}
                        />
                        <KpiTile
                            label="Keyword gains"
                            kpi={kpis?.keyword_gains ?? null}
                            drilldownTitle="Keyword gains trend"
                            drilldown={trendDrilldown('keyword_gains', 'Keyword gains')}
                        />
                        <KpiTile
                            label="Keyword losses"
                            kpi={kpis?.keyword_losses ?? null}
                            drilldownTitle="Keyword losses trend"
                            drilldown={trendDrilldown('keyword_losses', 'Keyword losses')}
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <h3 className="mb-3 text-sm font-semibold">Domain Rating trend</h3>
                            <TrendChart data={trend} dateKey="data_date" series={[{ key: 'domain_rating', name: 'Domain Rating' }]} />
                        </div>
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <h3 className="mb-3 text-sm font-semibold">Backlinks &amp; referring domains trend</h3>
                            <TrendChart
                                data={trend}
                                dateKey="data_date"
                                series={[
                                    { key: 'backlinks', name: 'Backlinks' },
                                    { key: 'referring_domains', name: 'Referring domains' },
                                ]}
                            />
                        </div>
                    </div>
                </>
            )}
        </MarketingStatisticsShell>
    );
}
