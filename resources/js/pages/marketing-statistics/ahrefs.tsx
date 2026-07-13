import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { type Kpi, type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';

export default function AhrefsReport({
    selected,
    websites,
    source,
    kpis,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    source: SourceStatus;
    kpis: Record<string, Kpi> | null;
}) {
    return (
        <MarketingStatisticsShell active="ahrefs" selected={selected} websites={websites} sources={{ ahrefs: source }}>
            {source.status === 'missing' && kpis === null ? (
                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border border-dashed p-6 text-center">
                    <p className="text-muted-foreground text-sm">
                        No Ahrefs data pipeline exists in BigQuery yet — this tab will populate automatically once one is built (see
                        ANALYTICS_BIGQUERY_FINDINGS.md).
                    </p>
                </div>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <KpiTile label="Domain Rating" kpi={kpis?.domain_rating ?? null} format={(v) => v.toFixed(1)} />
                    <KpiTile label="Backlinks" kpi={kpis?.backlinks ?? null} />
                    <KpiTile label="Referring domains" kpi={kpis?.referring_domains ?? null} />
                    <KpiTile label="Organic keywords" kpi={kpis?.organic_keywords ?? null} />
                    <KpiTile label="Estimated organic traffic" kpi={kpis?.estimated_organic_traffic ?? null} />
                    <KpiTile label="New backlinks" kpi={kpis?.new_backlinks ?? null} />
                    <KpiTile label="Lost backlinks" kpi={kpis?.lost_backlinks ?? null} />
                    <KpiTile label="Keyword gains" kpi={kpis?.keyword_gains ?? null} />
                    <KpiTile label="Keyword losses" kpi={kpis?.keyword_losses ?? null} />
                </div>
            )}
        </MarketingStatisticsShell>
    );
}
