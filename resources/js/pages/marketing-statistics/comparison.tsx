import { CategoryBarChart } from '@/components/marketing-statistics/category-bar-chart';
import { buildFilterQuery, MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { SortableHeader, useClientSort } from '@/components/sortable-header';
import { Skeleton } from '@/components/ui/skeleton';
import { type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';
import { Deferred, Link } from '@inertiajs/react';

type Ga4Summary = { users: number; sessions: number; engagement_rate: number | null };
type GscSummary = { clicks: number; impressions: number; average_position: number | null };

type ComparisonRow = {
    website_id: string;
    name: string;
    domain: string;
    ga4: Ga4Summary | null;
    gsc: GscSummary | null;
};

function compact(value: number | null | undefined): string {
    return value === null || value === undefined ? '—' : new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

export default function WebsiteComparison({
    selected,
    websites,
    comparison,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    // Deferred (two grouped live-view scans across every site) — undefined
    // until the partial reload lands.
    comparison?: { rows: ComparisonRow[]; sources: Record<string, SourceStatus> };
}) {
    const rows = comparison?.rows ?? [];
    const sources = comparison?.sources;
    // NOTE: a website with no GA4/GSC data (row.ga4/row.gsc null — source
    // down or not mapped) renders an identical zero-height bar to a website
    // that genuinely had zero users/clicks in range. CategoryBarChart has no
    // "missing" fill today, and giving it one (plus wiring a tri-state value
    // through) isn't a cheap change, so this is left as a known conflation
    // rather than half-fixed here. The per-row "—" in the table below (via
    // compact()) is the accurate signal in the meantime.
    const chartRows = rows.map((row) => ({
        name: row.name,
        ga4_users: row.ga4?.users ?? 0,
        gsc_clicks: row.gsc?.clicks ?? 0,
    }));

    const { sorted, sort, onSort } = useClientSort(rows, (row, column) => {
        switch (column) {
            case 'name':
                return row.name;
            case 'ga4_users':
                return row.ga4?.users ?? null;
            case 'ga4_sessions':
                return row.ga4?.sessions ?? null;
            case 'gsc_clicks':
                return row.gsc?.clicks ?? null;
            case 'gsc_impressions':
                return row.gsc?.impressions ?? null;
            default:
                return null;
        }
    });

    return (
        <MarketingStatisticsShell active="comparison" selected={selected} websites={websites} sources={sources}>
            <Deferred
                data="comparison"
                fallback={
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Skeleton className="h-[320px] rounded-xl" />
                        <Skeleton className="h-[320px] rounded-xl" />
                        <Skeleton className="h-[280px] rounded-xl lg:col-span-2" />
                    </div>
                }
            >
                <div className="flex flex-col gap-4">
            <div className="grid gap-4 lg:grid-cols-2">
                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h3 className="mb-3 text-sm font-semibold">GA4 users by website</h3>
                    <CategoryBarChart data={chartRows} labelKey="name" valueKey="ga4_users" valueLabel="users" />
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h3 className="mb-3 text-sm font-semibold">GSC clicks by website</h3>
                    <CategoryBarChart data={chartRows} labelKey="name" valueKey="gsc_clicks" valueLabel="clicks" />
                </div>
            </div>

            <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border p-4">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-muted-foreground text-left">
                            <SortableHeader column="name" sort={sort} onSort={onSort} className="py-1.5">
                                Website
                            </SortableHeader>
                            <SortableHeader column="ga4_users" sort={sort} onSort={onSort} className="py-1.5 text-right">
                                GA4 users
                            </SortableHeader>
                            <SortableHeader column="ga4_sessions" sort={sort} onSort={onSort} className="py-1.5 text-right">
                                GA4 sessions
                            </SortableHeader>
                            <SortableHeader column="gsc_clicks" sort={sort} onSort={onSort} className="py-1.5 text-right">
                                GSC clicks
                            </SortableHeader>
                            <SortableHeader column="gsc_impressions" sort={sort} onSort={onSort} className="py-1.5 text-right">
                                GSC impressions
                            </SortableHeader>
                        </tr>
                    </thead>
                    <tbody>
                        {sorted.map((row) => (
                            <tr key={row.website_id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                <td className="py-1.5">
                                    <Link
                                        href={`/marketing-statistics${buildFilterQuery({ ...selected, website_id: row.website_id })}`}
                                        className="text-brand-600 dark:text-brand-400 hover:underline"
                                    >
                                        {row.name}
                                    </Link>
                                </td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.ga4?.users)}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.ga4?.sessions)}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.gsc?.clicks)}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.gsc?.impressions)}</td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={5} className="text-muted-foreground py-3 text-center">
                                    {/* rows is always one entry per `websites` (see
                                        AnalyticsReportBuilder::websiteComparison) and registry() swallows its
                                        own fetch failures into the same empty array as "nothing configured" —
                                        no prop distinguishes the two cases here, so this copy stays generic
                                        until the backend surfaces the registry's own status. */}
                                    No mapped websites found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
                </div>
            </Deferred>
        </MarketingStatisticsShell>
    );
}
