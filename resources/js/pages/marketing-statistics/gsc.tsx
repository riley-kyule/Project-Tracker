import { CategoryPieChart } from '@/components/marketing-statistics/category-pie-chart';
import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { TrendChart } from '@/components/marketing-statistics/trend-chart';
import { Skeleton } from '@/components/ui/skeleton';
import { type Kpi, type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';
import { Deferred } from '@inertiajs/react';

const DEVICE_ORDER = ['desktop', 'mobile', 'tablet', 'smart tv'];

function pct(value: number): string {
    return `${(value * 100).toFixed(1)}%`;
}

type Breakdowns = {
    queries: { query: string; clicks: number; impressions: number; ctr: number | null; average_position: number | null }[];
    pages: { url: string; clicks: number; impressions: number; ctr: number | null }[];
    countries: { country: string; clicks: number; impressions: number }[];
    devices: { device: string; clicks: number; impressions: number }[];
};

function BreakdownCardShell({ title, children }: { title: string; children?: React.ReactNode }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            {children}
        </div>
    );
}

function BreakdownTable({ title, rows, columns }: { title: string; rows: Record<string, unknown>[]; columns: [string, string][] }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            <div className="max-h-64 overflow-x-auto overflow-y-auto">
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
                                        {typeof row[key] === 'number' && key.includes('ctr')
                                            ? `${((row[key] as number) * 100).toFixed(1)}%`
                                            : String(row[key] ?? '')}
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

export default function GscReport({
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
    trend: { data_date: string; clicks: number; impressions: number; average_position: number | null }[];
    breakdowns: Breakdowns | null;
}) {
    const ctrTrend = trend.map((row) => ({
        data_date: row.data_date,
        ctr: row.impressions ? row.clicks / row.impressions : 0,
    }));

    return (
        <MarketingStatisticsShell active="gsc" selected={selected} websites={websites} sources={{ gsc: source }}>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiTile
                    label="Clicks"
                    kpi={kpis?.clicks ?? null}
                    drilldownTitle="Clicks trend"
                    drilldown={<TrendChart data={trend} dateKey="data_date" series={[{ key: 'clicks', name: 'Clicks' }]} />}
                />
                <KpiTile
                    label="Impressions"
                    kpi={kpis?.impressions ?? null}
                    drilldownTitle="Impressions trend"
                    drilldown={<TrendChart data={trend} dateKey="data_date" series={[{ key: 'impressions', name: 'Impressions' }]} />}
                />
                <KpiTile
                    label="CTR"
                    kpi={kpis?.ctr ?? null}
                    format={pct}
                    drilldownTitle="CTR trend"
                    drilldown={<TrendChart data={ctrTrend} dateKey="data_date" series={[{ key: 'ctr', name: 'CTR' }]} valueFormat={pct} />}
                />
                <KpiTile
                    label="Average position"
                    kpi={kpis?.average_position ?? null}
                    format={(v) => v.toFixed(1)}
                    drilldownTitle="Average position trend"
                    drilldown={
                        <TrendChart
                            data={trend}
                            dateKey="data_date"
                            series={[{ key: 'average_position', name: 'Avg. position' }]}
                            valueFormat={(v) => v.toFixed(1)}
                        />
                    }
                />
            </div>

            <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <h3 className="mb-3 text-sm font-semibold">Clicks &amp; impressions trend</h3>
                <TrendChart
                    data={trend}
                    dateKey="data_date"
                    series={[
                        { key: 'clicks', name: 'Clicks' },
                        { key: 'impressions', name: 'Impressions' },
                    ]}
                />
            </div>

            <Deferred
                data="breakdowns"
                fallback={
                    <div className="grid gap-4 lg:grid-cols-2">
                        <BreakdownCardShell title="Queries" />
                        <BreakdownCardShell title="Pages" />
                        <BreakdownCardShell title="Countries" />
                        <BreakdownCardShell title="Devices (clicks)">
                            <Skeleton className="h-[260px] rounded-lg" />
                        </BreakdownCardShell>
                    </div>
                }
            >
                <>
                    {breakdowns && (
                        <div className="grid gap-4 lg:grid-cols-2">
                            <BreakdownTable
                                title="Queries"
                                rows={breakdowns.queries}
                                columns={[
                                    ['query', 'Query'],
                                    ['clicks', 'Clicks'],
                                    ['impressions', 'Impressions'],
                                    ['ctr', 'CTR'],
                                ]}
                            />
                            <BreakdownTable
                                title="Pages"
                                rows={breakdowns.pages}
                                columns={[
                                    ['url', 'Page'],
                                    ['clicks', 'Clicks'],
                                    ['impressions', 'Impressions'],
                                ]}
                            />
                            <BreakdownTable
                                title="Countries"
                                rows={breakdowns.countries}
                                columns={[
                                    ['country', 'Country'],
                                    ['clicks', 'Clicks'],
                                    ['impressions', 'Impressions'],
                                ]}
                            />
                            <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                                <h3 className="mb-3 text-sm font-semibold">Devices (clicks)</h3>
                                <CategoryPieChart data={breakdowns.devices} labelKey="device" valueKey="clicks" order={DEVICE_ORDER} />
                            </div>
                        </div>
                    )}
                </>
            </Deferred>
        </MarketingStatisticsShell>
    );
}
