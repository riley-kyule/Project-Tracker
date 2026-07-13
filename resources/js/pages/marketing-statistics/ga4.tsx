import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { TrendChart } from '@/components/marketing-statistics/trend-chart';
import { type Kpi, type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';

function pct(value: number): string {
    return `${(value * 100).toFixed(1)}%`;
}

type Breakdowns = {
    traffic_sources: { source: string; medium: string; users: number }[];
    devices: { device_category: string; users: number }[];
    landing_pages: { page_location: string; users: number; page_views: number }[];
    locations: { user_country: string; users: number }[];
};

function BreakdownTable({ title, rows, columns }: { title: string; rows: Record<string, unknown>[]; columns: [string, string][] }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            <div className="max-h-64 overflow-y-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-muted-foreground text-left">
                            {columns.map(([key, label]) => (
                                <th key={key} className={key === columns[0][0] ? 'py-1.5 font-medium' : 'py-1.5 text-right font-medium'}>
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, i) => (
                            <tr key={i} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                {columns.map(([key], colIndex) => (
                                    <td key={key} className={colIndex === 0 ? 'max-w-48 truncate py-1.5' : 'py-1.5 text-right tabular-nums'}>
                                        {String(row[key] ?? '')}
                                    </td>
                                ))}
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={columns.length} className="text-muted-foreground py-3 text-center">
                                    No data for this range.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function Ga4Report({
    selected,
    websites,
    source,
    kpis,
    trend,
    breakdowns,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    source: SourceStatus;
    kpis: Record<string, Kpi> | null;
    trend: { event_date: string; users: number; sessions: number; engaged_sessions: number }[];
    breakdowns: Breakdowns | null;
}) {
    return (
        <MarketingStatisticsShell active="ga4" selected={selected} websites={websites} sources={{ ga4: source }}>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiTile label="Aggregate Property Users" kpi={kpis?.aggregate_property_users ?? null} />
                <KpiTile label="Sessions" kpi={kpis?.sessions ?? null} />
                <KpiTile label="Key events" kpi={kpis?.key_events ?? null} />
                <KpiTile label="Engagement rate" kpi={kpis?.engagement_rate ?? null} format={pct} />
            </div>

            <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <h3 className="mb-3 text-sm font-semibold">Users &amp; sessions trend</h3>
                <TrendChart
                    data={trend}
                    dateKey="event_date"
                    series={[
                        { key: 'users', name: 'Users' },
                        { key: 'sessions', name: 'Sessions' },
                    ]}
                />
            </div>

            {breakdowns && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <BreakdownTable
                        title="Traffic sources"
                        rows={breakdowns.traffic_sources.map((r) => ({ label: `${r.source} / ${r.medium}`, users: r.users }))}
                        columns={[
                            ['label', 'Source / medium'],
                            ['users', 'Users'],
                        ]}
                    />
                    <BreakdownTable
                        title="Devices"
                        rows={breakdowns.devices}
                        columns={[
                            ['device_category', 'Device'],
                            ['users', 'Users'],
                        ]}
                    />
                    <BreakdownTable
                        title="Landing pages"
                        rows={breakdowns.landing_pages}
                        columns={[
                            ['page_location', 'Page'],
                            ['users', 'Users'],
                            ['page_views', 'Page views'],
                        ]}
                    />
                    <BreakdownTable
                        title="Visitor locations"
                        rows={breakdowns.locations}
                        columns={[
                            ['user_country', 'Country'],
                            ['users', 'Users'],
                        ]}
                    />
                </div>
            )}
        </MarketingStatisticsShell>
    );
}
