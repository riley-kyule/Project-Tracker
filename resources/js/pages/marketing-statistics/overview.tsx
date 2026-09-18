import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { buildFilterQuery, MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { TrendChart } from '@/components/marketing-statistics/trend-chart';
import { Skeleton } from '@/components/ui/skeleton';
import { type Kpi, type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';
import { Deferred } from '@inertiajs/react';

function pct(value: number): string {
    return `${(value * 100).toFixed(1)}%`;
}

function currency(value: number): string {
    return new Intl.NumberFormat('en', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(value);
}

type SourceKpis = { source: SourceStatus; kpis: Record<string, Kpi> | null };
type LocationRow = { user_country: string; users: number };

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

function TopLocationsList({ locations }: { locations?: LocationRow[] | null }) {
    const top = (locations ?? []).slice(0, 5);

    if (top.length === 0) {
        return <p className="text-muted-foreground text-sm">No location data for this range.</p>;
    }

    return (
        <ol className="space-y-1.5 text-sm">
            {top.map((row, i) => (
                <li key={row.user_country ?? i} className="flex items-center justify-between">
                    <span className="text-muted-foreground">
                        {i + 1}. {row.user_country || '(not set)'}
                    </span>
                    <span className="font-medium tabular-nums">{row.users.toLocaleString()}</span>
                </li>
            ))}
        </ol>
    );
}

function TopLocationsFallback() {
    return (
        <div className="space-y-1.5">
            {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className="h-5 w-full" />
            ))}
        </div>
    );
}

/**
 * Marketing's combined "one view" ask — Popcash CPM/Impressions matched
 * against GA4 Users, Key Event Rate, and Top locations. Overview is the
 * module's only page that already shows multiple sources side by side, so
 * this snapshot lives here rather than as its own tab (see the dedicated
 * GA4 and Popcash tabs for each source's full drilldown).
 */
function CampaignPerformanceSection({
    ga4,
    ga4Locations,
    popcash,
    query,
}: {
    ga4: Record<string, Kpi> | null;
    ga4Locations?: LocationRow[] | null;
    popcash?: SourceKpis;
    query: string;
}) {
    return (
        <div className="flex flex-col gap-3">
            <h2 className="text-sm font-semibold">Campaign Performance</h2>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiTile label="Users" kpi={ga4?.aggregate_property_users ?? null} href={`/marketing-statistics/ga4${query}`} />
                <KpiTile label="Key event rate" kpi={ga4?.key_event_rate ?? null} format={pct} href={`/marketing-statistics/ga4${query}`} />
                <Deferred data="popcash" fallback={<KpiTileSkeleton label="CPM" />}>
                    <KpiTile
                        label="CPM"
                        kpi={popcash?.kpis?.cpm ?? null}
                        format={currency}
                        href={`/marketing-statistics/popcash${query}`}
                    />
                </Deferred>
                <Deferred data="popcash" fallback={<KpiTileSkeleton label="Impressions" />}>
                    <KpiTile label="Impressions" kpi={popcash?.kpis?.impressions ?? null} href={`/marketing-statistics/popcash${query}`} />
                </Deferred>
            </div>
            <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <h3 className="mb-3 text-sm font-semibold">Top locations</h3>
                <Deferred data="ga4_locations" fallback={<TopLocationsFallback />}>
                    <TopLocationsList locations={ga4Locations} />
                </Deferred>
            </div>
        </div>
    );
}

export default function Overview({
    selected,
    websites,
    ga4_source,
    ga4,
    ga4_trend,
    ga4_locations,
    gsc,
    ahrefs,
    ahrefs_enabled,
    popcash,
    popcash_enabled,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    ga4_source: SourceStatus;
    ga4: Record<string, Kpi> | null;
    ga4_trend: { event_date: string; users: number; sessions: number }[];
    ga4_locations?: LocationRow[] | null;
    gsc?: SourceKpis;
    ahrefs?: SourceKpis;
    ahrefs_enabled: boolean;
    popcash?: SourceKpis;
    popcash_enabled: boolean;
}) {
    const query = buildFilterQuery(selected);
    const sources: Record<string, SourceStatus> = { ga4: ga4_source };
    if (gsc) sources.gsc = gsc.source;
    if (ahrefs) sources.ahrefs = ahrefs.source;
    if (popcash) sources.popcash = popcash.source;

    return (
        <MarketingStatisticsShell active="overview" selected={selected} websites={websites} sources={sources}>
            {popcash_enabled && <CampaignPerformanceSection ga4={ga4} ga4Locations={ga4_locations} popcash={popcash} query={query} />}

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
                    <KpiTile label="Key event rate" kpi={ga4?.key_event_rate ?? null} format={pct} href={`/marketing-statistics/ga4${query}`} />
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
