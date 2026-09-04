import { CategoryBarChart } from '@/components/marketing-statistics/category-bar-chart';
import { CategoryPieChart } from '@/components/marketing-statistics/category-pie-chart';
import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { TrendChart } from '@/components/marketing-statistics/trend-chart';
import { SortableHeader, useClientSort } from '@/components/sortable-header';
import { Skeleton } from '@/components/ui/skeleton';
import { type Kpi, type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';
import { Deferred } from '@inertiajs/react';

const DEVICE_ORDER = ['desktop', 'mobile', 'tablet', 'smart tv'];

function pct(value: number): string {
    return `${(value * 100).toFixed(1)}%`;
}

type Breakdowns = {
    traffic_sources: { source: string; medium: string; users: number }[];
    devices: { device_category: string; users: number }[];
    landing_pages: { page_location: string; users: number; page_views: number }[];
    locations: { user_country: string; users: number }[];
    key_events: { key_event: string; key_event_category: string; key_event_count: number; users: number }[];
};

function BreakdownCardShell({ title, children }: { title: string; children?: React.ReactNode }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            {children}
        </div>
    );
}

function BreakdownSkeleton({ title }: { title: string }) {
    return (
        <BreakdownCardShell title={title}>
            <Skeleton className="h-[260px] rounded-lg" />
        </BreakdownCardShell>
    );
}

function BreakdownTable({ title, rows, columns }: { title: string; rows: Record<string, unknown>[]; columns: [string, string][] }) {
    const { sorted, sort, onSort } = useClientSort(rows, (row, column) => {
        const v = row[column];
        return v == null ? null : typeof v === 'number' ? v : String(v);
    });

    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            <div className="max-h-64 overflow-x-auto overflow-y-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-muted-foreground text-left">
                            {columns.map(([key, label], i) => (
                                <SortableHeader
                                    key={key}
                                    column={key}
                                    sort={sort}
                                    onSort={onSort}
                                    className={i === 0 ? 'py-1.5' : 'py-1.5 [&>button]:ml-auto'}
                                >
                                    {label}
                                </SortableHeader>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {sorted.map((row, i) => (
                            <tr key={i} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                {columns.map(([key], colIndex) => (
                                    <td
                                        key={key}
                                        className={colIndex === 0 ? 'max-w-48 truncate py-1.5' : 'py-1.5 text-right tabular-nums'}
                                        title={colIndex === 0 ? String(row[key] ?? '') : undefined}
                                    >
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
    traffic_sources,
    devices,
    landing_pages,
    locations,
    key_events,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    source: SourceStatus;
    kpis: Record<string, Kpi> | null;
    trend: { event_date: string; users: number; sessions: number; engaged_sessions: number }[];
} & Partial<Breakdowns>) {
    const engagementTrend = trend.map((row) => ({
        event_date: row.event_date,
        engagement_rate: row.sessions ? row.engaged_sessions / row.sessions : 0,
    }));

    return (
        <MarketingStatisticsShell active="ga4" selected={selected} websites={websites} sources={{ ga4: source }}>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiTile
                    label="Aggregate Property Users"
                    kpi={kpis?.aggregate_property_users ?? null}
                    drilldownTitle="Users trend"
                    drilldown={<TrendChart data={trend} dateKey="event_date" series={[{ key: 'users', name: 'Users' }]} />}
                />
                <KpiTile
                    label="Sessions"
                    kpi={kpis?.sessions ?? null}
                    drilldownTitle="Sessions trend"
                    drilldown={<TrendChart data={trend} dateKey="event_date" series={[{ key: 'sessions', name: 'Sessions' }]} />}
                />
                <KpiTile
                    label="Key events"
                    kpi={kpis?.key_events ?? null}
                    drilldownTitle="Key events breakdown"
                    drilldown={
                        <CategoryBarChart data={key_events ?? []} labelKey="key_event" valueKey="key_event_count" valueLabel="events" />
                    }
                />
                <KpiTile
                    label="Engagement rate"
                    kpi={kpis?.engagement_rate ?? null}
                    format={pct}
                    drilldownTitle="Engagement rate trend"
                    drilldown={
                        <TrendChart
                            data={engagementTrend}
                            dateKey="event_date"
                            series={[{ key: 'engagement_rate', name: 'Engagement rate' }]}
                            valueFormat={pct}
                        />
                    }
                />
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

            <div className="grid gap-4 lg:grid-cols-2">
                <Deferred data="traffic_sources" fallback={<BreakdownSkeleton title="Traffic sources (users)" />}>
                    <BreakdownCardShell title="Traffic sources (users)">
                        <CategoryBarChart
                            data={(traffic_sources ?? []).map((r) => ({ label: `${r.source} / ${r.medium}`, users: r.users }))}
                            labelKey="label"
                            valueKey="users"
                            valueLabel="users"
                        />
                    </BreakdownCardShell>
                </Deferred>
                <Deferred data="devices" fallback={<BreakdownSkeleton title="Devices (users)" />}>
                    <BreakdownCardShell title="Devices (users)">
                        <CategoryPieChart data={devices ?? []} labelKey="device_category" valueKey="users" order={DEVICE_ORDER} />
                    </BreakdownCardShell>
                </Deferred>
                <Deferred data="landing_pages" fallback={<BreakdownSkeleton title="Landing pages" />}>
                    <BreakdownTable
                        title="Landing pages"
                        rows={landing_pages ?? []}
                        columns={[
                            ['page_location', 'Page'],
                            ['users', 'Users'],
                            ['page_views', 'Page views'],
                        ]}
                    />
                </Deferred>
                <Deferred data="locations" fallback={<BreakdownSkeleton title="Visitor locations" />}>
                    <BreakdownTable
                        title="Visitor locations"
                        rows={locations ?? []}
                        columns={[
                            ['user_country', 'Country'],
                            ['users', 'Users'],
                        ]}
                    />
                </Deferred>
            </div>
        </MarketingStatisticsShell>
    );
}
