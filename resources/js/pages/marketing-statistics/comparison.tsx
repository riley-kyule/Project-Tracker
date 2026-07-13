import { buildFilterQuery, MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { type Kpi, type MarketingFilters, type MarketingWebsite } from '@/types/marketing-statistics';
import { Link } from '@inertiajs/react';

type ComparisonRow = {
    website_id: string;
    name: string;
    domain: string;
    ga4: Record<string, Kpi> | null;
    gsc: Record<string, Kpi> | null;
};

function compact(value: number | null | undefined): string {
    return value === null || value === undefined ? '—' : new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

export default function WebsiteComparison({
    selected,
    websites,
    rows,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    rows: ComparisonRow[];
}) {
    return (
        <MarketingStatisticsShell active="comparison" selected={selected} websites={websites}>
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
                                <td className="py-1.5 text-right tabular-nums">{compact(row.ga4?.aggregate_property_users.current)}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.ga4?.sessions.current)}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.gsc?.clicks.current)}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.gsc?.impressions.current)}</td>
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
