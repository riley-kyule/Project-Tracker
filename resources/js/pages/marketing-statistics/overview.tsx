import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { buildFilterQuery, MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { TrendChart } from '@/components/marketing-statistics/trend-chart';
import { Skeleton } from '@/components/ui/skeleton';
import { type Kpi, type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';
import { Deferred } from '@inertiajs/react';

function pct(value: number): string {
    return `${(value * 100).toFixed(1)}%`;
}

type SourceKpis = { source: SourceStatus; kpis: Record<string, Kpi> | null };

/** Loading placeholder shaped like KpiTile — distinct from KpiTile's own "—" so a still-loading
 *  tile can't be mistaken for a tile that genuinely has no data. */
function KpiTileSkeleton({ label }: { label: string }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border h-full rounded-xl border p-4">
            <Skeleton className="h-8 w-16" />
            <div className="text-muted-foreground mt-1 text-sm">{label}</div>
            <Skeleton className="mt-2 h-4 w-20" />
        </div>
    );
}

function GscTiles({ gsc, query }: { gsc?: SourceKpis; query: string }) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiTile label="Clicks" kpi={gsc?.kpis?.clicks ?? null} href={`/marketing-statistics/gsc${query}`} />
            <KpiTile label="Impressions" kpi={gsc?.kpis?.impressions ?? null} href={`/marketing-statistics/gsc${query}`} />
            <KpiTile label="CTR" kpi={gsc?.kpis?.ctr ?? null} format={pct} href={`/marketing-statistics/gsc${query}`} />
            <KpiTile
                label="Average position"
                kpi={gsc?.kpis?.average_position ?? null}
                format={(v) => v.toFixed(1)}
                href={`/marketing-statistics/gsc${query}`}
            />
        </div>
    );
}

function GscTilesFallback() {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiTileSkeleton label="Clicks" />
            <KpiTileSkeleton label="Impressions" />
            <KpiTileSkeleton label="CTR" />
            <KpiTileSkeleton label="Average position" />
        </div>
    );
}

function AhrefsTiles({ ahrefs, query }: { ahrefs?: SourceKpis; query: string }) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiTile
                label="Domain Rating"
                kpi={ahrefs?.kpis?.domain_rating ?? null}
                format={(v) => v.toFixed(1)}
                href={`/marketing-statistics/ahrefs${query}`}
            />
            <KpiTile label="Backlinks" kpi={ahrefs?.kpis?.backlinks ?? null} href={`/marketing-statistics/ahrefs${query}`} />
            <KpiTile label="Referring domains" kpi={ahrefs?.kpis?.referring_domains ?? null} href={`/marketing-statistics/ahrefs${query}`} />
            <KpiTile label="Organic keywords" kpi={ahrefs?.kpis?.organic_keywords ?? null} href={`/marketing-statistics/ahrefs${query}`} />
        </div>
    );
}

function AhrefsTilesFallback() {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <KpiTileSkeleton label="Domain Rating" />
            <KpiTileSkeleton label="Backlinks" />
            <KpiTileSkeleton label="Referring domains" />
            <KpiTileSkeleton label="Organic keywords" />
        </div>
    );
}

export default function Overview({
    selected,
    websites,
    ga4_source,
    ga4,
    ga4_trend,
    gsc,
    ahrefs,
    ahrefs_enabled,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    ga4_source: SourceStatus;
    ga4: Record<string, Kpi> | null;
    ga4_trend: { event_date: string; users: number; sessions: number }[];
    gsc?: SourceKpis;
    ahrefs?: SourceKpis;
    ahrefs_enabled: boolean;
}) {
    const query = buildFilterQuery(selected);
    const sources: Record<string, SourceStatus> = { ga4: ga4_source };
    if (gsc) sources.gsc = gsc.source;
    if (ahrefs) sources.ahrefs = ahrefs.source;

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
                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="text-sm font-semibold">Users &amp; sessions trend</h3>
                        <a href={`/marketing-statistics/ga4${query}`} className="text-brand-600 dark:text-brand-400 text-xs hover:underline">
                            View full report →
                        </a>
                    </div>
                    <TrendChart
                        data={ga4_trend}
                        dateKey="event_date"
                        series={[
                            { key: 'users', name: 'Users' },
                            { key: 'sessions', name: 'Sessions' },
                        ]}
                    />
                </div>
            </div>

            <div className="flex flex-col gap-3">
                <h2 className="text-sm font-semibold">Google Search Console</h2>
                <Deferred data="gsc" fallback={<GscTilesFallback />}>
                    <GscTiles gsc={gsc} query={query} />
                </Deferred>
            </div>

            {ahrefs_enabled && (
                <div className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold">Ahrefs</h2>
                    <Deferred data="ahrefs" fallback={<AhrefsTilesFallback />}>
                        <AhrefsTiles ahrefs={ahrefs} query={query} />
                    </Deferred>
                </div>
            )}
        </MarketingStatisticsShell>
    );
}
