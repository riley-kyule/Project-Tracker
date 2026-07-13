import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { buildFilterQuery, MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { type Kpi, type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';

function pct(value: number): string {
    return `${(value * 100).toFixed(1)}%`;
}

export default function Overview({
    selected,
    websites,
    sources,
    ga4,
    gsc,
    ahrefs,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    sources: Record<string, SourceStatus>;
    ga4: Record<string, Kpi> | null;
    gsc: Record<string, Kpi> | null;
    ahrefs: Record<string, Kpi> | null;
}) {
    const query = buildFilterQuery(selected);

    return (
        <MarketingStatisticsShell active="overview" selected={selected} websites={websites} sources={sources}>
            <div className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold">GA4</h2>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiTile
                        label="Aggregate Property Users"
                        kpi={ga4?.aggregate_property_users ?? null}
                        href={`/marketing-statistics/ga4${query}`}
                    />
                    <KpiTile label="Sessions" kpi={ga4?.sessions ?? null} href={`/marketing-statistics/ga4${query}`} />
                    <KpiTile label="Key events" kpi={ga4?.key_events ?? null} href={`/marketing-statistics/ga4${query}`} />
                    <KpiTile label="Engagement rate" kpi={ga4?.engagement_rate ?? null} format={pct} href={`/marketing-statistics/ga4${query}`} />
                </div>
            </div>

            <div className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold">Google Search Console</h2>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiTile label="Clicks" kpi={gsc?.clicks ?? null} href={`/marketing-statistics/gsc${query}`} />
                    <KpiTile label="Impressions" kpi={gsc?.impressions ?? null} href={`/marketing-statistics/gsc${query}`} />
                    <KpiTile label="CTR" kpi={gsc?.ctr ?? null} format={pct} href={`/marketing-statistics/gsc${query}`} />
                    <KpiTile
                        label="Average position"
                        kpi={gsc?.average_position ?? null}
                        format={(v) => v.toFixed(1)}
                        href={`/marketing-statistics/gsc${query}`}
                    />
                </div>
            </div>

            <div className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold">Ahrefs</h2>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiTile
                        label="Domain Rating"
                        kpi={ahrefs?.domain_rating ?? null}
                        format={(v) => v.toFixed(1)}
                        href={`/marketing-statistics/ahrefs${query}`}
                    />
                    <KpiTile label="Backlinks" kpi={ahrefs?.backlinks ?? null} href={`/marketing-statistics/ahrefs${query}`} />
                    <KpiTile label="Referring domains" kpi={ahrefs?.referring_domains ?? null} href={`/marketing-statistics/ahrefs${query}`} />
                    <KpiTile label="Organic keywords" kpi={ahrefs?.organic_keywords ?? null} href={`/marketing-statistics/ahrefs${query}`} />
                </div>
            </div>
        </MarketingStatisticsShell>
    );
}
