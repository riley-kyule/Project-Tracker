import { CategoryBarChart } from '@/components/marketing-statistics/category-bar-chart';
import { buildFilterQuery, MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';
import { Link } from '@inertiajs/react';

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
    rows,
    sources,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    rows: ComparisonRow[];
    sources?: Record<string, SourceStatus>;
}) {
    const chartRows = rows.map((row) => ({
        name: row.name,
        ga4_users: row.ga4?.users ?? 0,
        gsc_clicks: row.gsc?.clicks ?? 0,
    }));

    return (
        <MarketingStatisticsShell active="comparison" selected={selected} websites={websites} sources={sources}>
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
                            <th className="py-1.5 font-medium">Website</th>
                            <th className="py-1.5 text-right font-medium">GA4 users</th>
                            <th className="py-1.5 text-right font-medium">GA4 sessions</th>
                            <th className="py-1.5 text-right font-medium">GSC clicks</th>
                            <th className="py-1.5 text-right font-medium">GSC impressions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
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
                                    No mapped websites found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </MarketingStatisticsShell>
    );
}
