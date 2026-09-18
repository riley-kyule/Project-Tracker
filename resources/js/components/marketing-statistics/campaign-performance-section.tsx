import { KpiTile, KpiTileSkeleton } from '@/components/marketing-statistics/kpi-tile';
import { Skeleton } from '@/components/ui/skeleton';
import { type Kpi, type SourceKpis } from '@/types/marketing-statistics';
import { Deferred } from '@inertiajs/react';

export type LocationRow = { user_country: string; users: number };

function pct(value: number): string {
    return `${(value * 100).toFixed(1)}%`;
}

function currency(value: number): string {
    return new Intl.NumberFormat('en', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(value);
}

export function TopLocationsList({ locations }: { locations?: LocationRow[] | null }) {
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

export function TopLocationsFallback() {
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
 * against Popcash-*attributed* GA4 Users, Key Event Rate, and Top locations
 * (traffic where source/medium = config('analytics.api.popcash.ga4_*'), not
 * whole-site GA4 — see AnalyticsReportBuilder::popcashGa4Report()). Shared by
 * the Overview page (Popcash eager, GA4/locations deferred as `campaign_ga4`/
 * `campaign_ga4_locations`) and the Popcash tab (Popcash eager, same GA4
 * props deferred) — `deferPopcash` says whether `popcash` is itself an
 * Inertia-deferred prop of that name on the page it's rendered from, so the
 * right half gets a loading skeleton instead of a permanent stuck-empty state.
 */
export function CampaignPerformanceSection({
    ga4,
    ga4Locations,
    popcash,
    query,
    deferPopcash = false,
}: {
    ga4?: Record<string, Kpi> | null;
    ga4Locations?: LocationRow[] | null;
    popcash?: SourceKpis;
    query: string;
    /** Pass true only when `popcash` is itself an Inertia-deferred prop named "popcash" on this page. */
    deferPopcash?: boolean;
}) {
    const popcashTiles = (
        <>
            <KpiTile label="CPM" kpi={popcash?.kpis?.cpm ?? null} format={currency} href={`/marketing-statistics/popcash${query}`} />
            <KpiTile label="Impressions" kpi={popcash?.kpis?.impressions ?? null} href={`/marketing-statistics/popcash${query}`} />
        </>
    );

    return (
        <div className="flex flex-col gap-3">
            <h2 className="text-sm font-semibold">Campaign Performance</h2>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Deferred
                    data="campaign_ga4"
                    fallback={
                        <>
                            <KpiTileSkeleton label="Users" />
                            <KpiTileSkeleton label="Key event rate" />
                        </>
                    }
                >
                    <>
                        <KpiTile label="Users" kpi={ga4?.aggregate_property_users ?? null} href={`/marketing-statistics/ga4${query}`} />
                        <KpiTile
                            label="Key event rate"
                            kpi={ga4?.key_event_rate ?? null}
                            format={pct}
                            href={`/marketing-statistics/ga4${query}`}
                        />
                    </>
                </Deferred>
                {deferPopcash ? (
                    <Deferred
                        data="popcash"
                        fallback={
                            <>
                                <KpiTileSkeleton label="CPM" />
                                <KpiTileSkeleton label="Impressions" />
                            </>
                        }
                    >
                        {popcashTiles}
                    </Deferred>
                ) : (
                    popcashTiles
                )}
            </div>
            <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <h3 className="mb-3 text-sm font-semibold">Top locations</h3>
                <Deferred data="campaign_ga4_locations" fallback={<TopLocationsFallback />}>
                    <TopLocationsList locations={ga4Locations} />
                </Deferred>
            </div>
        </div>
    );
}
