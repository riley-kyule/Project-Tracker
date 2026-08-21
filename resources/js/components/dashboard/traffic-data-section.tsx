import { CategoryBarChart } from '@/components/marketing-statistics/category-bar-chart';
import { CategoryPieChart } from '@/components/marketing-statistics/category-pie-chart';
import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { TrendChart } from '@/components/marketing-statistics/trend-chart';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useSourceStatusToasts } from '@/hooks/use-source-status-toasts';
import { type Kpi, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';
import { RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

const ALL_WEBSITES = 'all';
const DEVICE_ORDER = ['desktop', 'mobile', 'tablet', 'smart tv'];

const DATE_PRESETS = [
    { key: 'last_7_days', label: 'Last 7 days', days: 7 },
    { key: 'last_30_days', label: 'Last 30 days', days: 30 },
    { key: 'last_90_days', label: 'Last 90 days', days: 90 },
    { key: 'custom', label: 'Custom range', days: null },
] as const;

const COMPARISON_OPTIONS = [
    { value: 'none', label: 'No comparison' },
    { value: 'previous_period', label: 'Previous period' },
    { value: 'previous_year', label: 'Previous year' },
] as const;

type Ga4Trend = { event_date: string; users: number; sessions: number; engaged_sessions: number };
type GscTrend = { data_date: string; clicks: number; impressions: number; average_position: number | null };

type ReportResponse = {
    ga4: { source: SourceStatus; kpis: Record<string, Kpi> | null; trend: Ga4Trend[] };
    gsc: { source: SourceStatus; kpis: Record<string, Kpi> | null; trend: GscTrend[] };
};

type Ga4Breakdowns = {
    traffic_sources: { source: string; medium: string; users: number }[];
    devices: { device_category: string; users: number }[];
    landing_pages: { page_location: string; users: number; page_views: number }[];
    locations: { user_country: string; users: number }[];
    key_events: { key_event: string; key_event_category: string; key_event_count: number; users: number }[];
};

type GscBreakdowns = {
    queries: { query: string; clicks: number; impressions: number; ctr: number | null; average_position: number | null }[];
    pages: { url: string; clicks: number; impressions: number; ctr: number | null }[];
    countries: { country: string; clicks: number; impressions: number }[];
    devices: { device: string; clicks: number; impressions: number }[];
};

type ComparisonRow = {
    website_id: string;
    name: string;
    domain: string;
    ga4: { users: number; sessions: number; engagement_rate: number | null } | null;
    gsc: { clicks: number; impressions: number; average_position: number | null } | null;
};

type BreakdownsResponse = {
    ga4: Ga4Breakdowns | null;
    gsc: GscBreakdowns | null;
    comparison: { rows: ComparisonRow[]; sources: { ga4: SourceStatus; gsc: SourceStatus } };
};

function pct(value: number): string {
    return `${(value * 100).toFixed(1)}%`;
}

function compact(value: number | null | undefined): string {
    return value === null || value === undefined
        ? '—'
        : new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

function toDateInput(date: Date): string {
    return date.toISOString().slice(0, 10);
}

function SectionCard({ title, children }: { title: string; children?: React.ReactNode }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            {children}
        </div>
    );
}

function BreakdownTable({ title, rows, columns }: { title: string; rows: Record<string, unknown>[]; columns: [string, string][] }) {
    return (
        <SectionCard title={title}>
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
        </SectionCard>
    );
}

export function TrafficDataSection() {
    const [websites, setWebsites] = useState<MarketingWebsite[]>([]);
    const [websiteDomain, setWebsiteDomain] = useState<string>(ALL_WEBSITES);

    const [datePreset, setDatePreset] = useState<(typeof DATE_PRESETS)[number]['key']>('last_30_days');
    const [customFrom, setCustomFrom] = useState(() => toDateInput(new Date(Date.now() - 30 * 86400000)));
    const [customTo, setCustomTo] = useState(() => toDateInput(new Date(Date.now() - 86400000)));
    const [comparisonPeriod, setComparisonPeriod] = useState<(typeof COMPARISON_OPTIONS)[number]['value']>('none');

    const [report, setReport] = useState<ReportResponse | null>(null);
    const [reportLoading, setReportLoading] = useState(false);
    const [breakdowns, setBreakdowns] = useState<BreakdownsResponse | null>(null);
    const [breakdownsLoading, setBreakdownsLoading] = useState(false);

    const preset = DATE_PRESETS.find((p) => p.key === datePreset);
    let dateFrom: string;
    let dateTo: string;
    if (!preset || preset.days === null) {
        dateFrom = customFrom;
        dateTo = customTo;
    } else {
        const to = new Date(Date.now() - 86400000);
        const from = new Date(to.getTime() - (preset.days - 1) * 86400000);
        dateFrom = toDateInput(from);
        dateTo = toDateInput(to);
    }

    useEffect(() => {
        fetch('/dashboards/ceo/traffic-data/websites', { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((payload: { websites: MarketingWebsite[] }) => setWebsites(payload.websites))
            .catch(() => setWebsites([]));
    }, []);

    // GA4/GSC results are cached server-side until end of day (AnalyticsCache)
    // — refresh=1 is the manual bypass, same as Marketing Statistics' Refresh
    // button, for "I know this changed, don't wait until tomorrow."
    const loadReport = useCallback(
        (forceRefresh: boolean) => {
            setReportLoading(true);
            const params = new URLSearchParams({
                website_domain: websiteDomain,
                date_from: dateFrom,
                date_to: dateTo,
                comparison_period: comparisonPeriod,
                ...(forceRefresh ? { refresh: '1' } : {}),
            });

            fetch(`/dashboards/ceo/traffic-data?${params}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((payload: ReportResponse) => setReport(payload))
                .finally(() => setReportLoading(false));
        },
        [websiteDomain, dateFrom, dateTo, comparisonPeriod],
    );

    const loadBreakdowns = useCallback(
        (forceRefresh: boolean) => {
            setBreakdownsLoading(true);
            const params = new URLSearchParams({
                website_domain: websiteDomain,
                date_from: dateFrom,
                date_to: dateTo,
                ...(forceRefresh ? { refresh: '1' } : {}),
            });

            fetch(`/dashboards/ceo/traffic-data/breakdowns?${params}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((payload: BreakdownsResponse) => setBreakdowns(payload))
                .finally(() => setBreakdownsLoading(false));
        },
        [websiteDomain, dateFrom, dateTo],
    );

    useEffect(() => {
        loadReport(false);
    }, [loadReport]);

    useEffect(() => {
        loadBreakdowns(false);
    }, [loadBreakdowns]);

    useSourceStatusToasts(report ? { ga4: report.ga4.source, gsc: report.gsc.source } : undefined);

    const refresh = () => {
        loadReport(true);
        loadBreakdowns(true);
    };

    const engagementTrend = (report?.ga4.trend ?? []).map((row) => ({
        event_date: row.event_date,
        engagement_rate: row.sessions ? row.engaged_sessions / row.sessions : 0,
    }));
    const ctrTrend = (report?.gsc.trend ?? []).map((row) => ({
        data_date: row.data_date,
        ctr: row.impressions ? row.clicks / row.impressions : 0,
    }));

    const comparisonRows = breakdowns?.comparison.rows ?? [];
    const comparisonChartRows = comparisonRows.map((row) => ({
        name: row.name,
        ga4_users: row.ga4?.users ?? 0,
        gsc_clicks: row.gsc?.clicks ?? 0,
    }));

    const loading = reportLoading || breakdownsLoading;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold">Traffic data</h2>
                <div className="flex items-center gap-2">
                    {loading && <span className="text-muted-foreground text-xs">Refreshing…</span>}
                    <button
                        type="button"
                        onClick={refresh}
                        disabled={loading}
                        title="Cached until end of day — refresh to pull it live now"
                        className="text-muted-foreground hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                    >
                        <RefreshCw className={`size-3.5 ${loading ? 'animate-spin' : ''}`} />
                        <span className="sr-only">Refresh traffic data</span>
                    </button>
                </div>
            </div>

            {/* Filters: one row, above every section below — they all scope to the same slice. */}
            <div className="flex flex-wrap items-center gap-2">
                <Select value={websiteDomain} onValueChange={setWebsiteDomain}>
                    <SelectTrigger className="w-56">
                        <SelectValue placeholder="Select a website" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_WEBSITES}>All Platforms</SelectItem>
                        {websites.map((website) => (
                            <SelectItem key={website.domain} value={website.domain}>
                                {website.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={datePreset} onValueChange={(value) => setDatePreset(value as typeof datePreset)}>
                    <SelectTrigger className="w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {DATE_PRESETS.map((p) => (
                            <SelectItem key={p.key} value={p.key}>
                                {p.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {datePreset === 'custom' && (
                    <>
                        <input
                            type="date"
                            value={customFrom}
                            max={customTo}
                            onChange={(e) => setCustomFrom(e.target.value)}
                            className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                        />
                        <span className="text-muted-foreground text-sm">to</span>
                        <input
                            type="date"
                            value={customTo}
                            min={customFrom}
                            max={toDateInput(new Date(Date.now() - 86400000))}
                            onChange={(e) => setCustomTo(e.target.value)}
                            className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                        />
                    </>
                )}

                <Select value={comparisonPeriod} onValueChange={(value) => setComparisonPeriod(value as typeof comparisonPeriod)}>
                    <SelectTrigger className="w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {COMPARISON_OPTIONS.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {/* GA4 */}
            <h3 className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">GA4 traffic</h3>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiTile
                    label="Users"
                    kpi={report?.ga4.kpis?.aggregate_property_users ?? null}
                    drilldownTitle="Users trend"
                    drilldown={<TrendChart data={report?.ga4.trend ?? []} dateKey="event_date" series={[{ key: 'users', name: 'Users' }]} />}
                />
                <KpiTile
                    label="Sessions"
                    kpi={report?.ga4.kpis?.sessions ?? null}
                    drilldownTitle="Sessions trend"
                    drilldown={<TrendChart data={report?.ga4.trend ?? []} dateKey="event_date" series={[{ key: 'sessions', name: 'Sessions' }]} />}
                />
                <KpiTile
                    label="Key events"
                    kpi={report?.ga4.kpis?.key_events ?? null}
                    drilldownTitle="Key events breakdown"
                    drilldown={
                        <CategoryBarChart
                            data={breakdowns?.ga4?.key_events ?? []}
                            labelKey="key_event"
                            valueKey="key_event_count"
                            valueLabel="events"
                        />
                    }
                />
                <KpiTile
                    label="Engagement rate"
                    kpi={report?.ga4.kpis?.engagement_rate ?? null}
                    format={pct}
                    drilldownTitle="Engagement rate trend"
                    drilldown={
                        <TrendChart data={engagementTrend} dateKey="event_date" series={[{ key: 'engagement_rate', name: 'Engagement rate' }]} valueFormat={pct} />
                    }
                />
            </div>

            <SectionCard title="Users & sessions trend">
                <TrendChart
                    data={report?.ga4.trend ?? []}
                    dateKey="event_date"
                    series={[
                        { key: 'users', name: 'Users' },
                        { key: 'sessions', name: 'Sessions' },
                    ]}
                />
            </SectionCard>

            {breakdownsLoading && !breakdowns ? (
                <div className="grid gap-4 lg:grid-cols-2">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <Skeleton key={i} className="h-72 rounded-xl" />
                    ))}
                </div>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    <SectionCard title="Traffic sources (users)">
                        <CategoryBarChart
                            data={(breakdowns?.ga4?.traffic_sources ?? []).map((r) => ({ label: `${r.source} / ${r.medium}`, users: r.users }))}
                            labelKey="label"
                            valueKey="users"
                            valueLabel="users"
                        />
                    </SectionCard>
                    <SectionCard title="Devices (users)">
                        <CategoryPieChart data={breakdowns?.ga4?.devices ?? []} labelKey="device_category" valueKey="users" order={DEVICE_ORDER} />
                    </SectionCard>
                    <BreakdownTable
                        title="Landing pages"
                        rows={breakdowns?.ga4?.landing_pages ?? []}
                        columns={[
                            ['page_location', 'Page'],
                            ['users', 'Users'],
                            ['page_views', 'Page views'],
                        ]}
                    />
                    <BreakdownTable
                        title="Visitor locations"
                        rows={breakdowns?.ga4?.locations ?? []}
                        columns={[
                            ['user_country', 'Country'],
                            ['users', 'Users'],
                        ]}
                    />
                </div>
            )}

            {/* GSC */}
            <h3 className="text-muted-foreground mt-2 text-xs font-semibold tracking-wide uppercase">Google Search Console</h3>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiTile
                    label="Clicks"
                    kpi={report?.gsc.kpis?.clicks ?? null}
                    drilldownTitle="Clicks trend"
                    drilldown={<TrendChart data={report?.gsc.trend ?? []} dateKey="data_date" series={[{ key: 'clicks', name: 'Clicks' }]} />}
                />
                <KpiTile
                    label="Impressions"
                    kpi={report?.gsc.kpis?.impressions ?? null}
                    drilldownTitle="Impressions trend"
                    drilldown={
                        <TrendChart data={report?.gsc.trend ?? []} dateKey="data_date" series={[{ key: 'impressions', name: 'Impressions' }]} />
                    }
                />
                <KpiTile
                    label="CTR"
                    kpi={report?.gsc.kpis?.ctr ?? null}
                    format={pct}
                    drilldownTitle="CTR trend"
                    drilldown={<TrendChart data={ctrTrend} dateKey="data_date" series={[{ key: 'ctr', name: 'CTR' }]} valueFormat={pct} />}
                />
                <KpiTile
                    label="Average position"
                    kpi={report?.gsc.kpis?.average_position ?? null}
                    format={(v) => v.toFixed(1)}
                    drilldownTitle="Average position trend"
                    drilldown={
                        <TrendChart
                            data={report?.gsc.trend ?? []}
                            dateKey="data_date"
                            series={[{ key: 'average_position', name: 'Avg. position' }]}
                            valueFormat={(v) => v.toFixed(1)}
                        />
                    }
                />
            </div>

            <SectionCard title="Clicks & impressions trend">
                <TrendChart
                    data={report?.gsc.trend ?? []}
                    dateKey="data_date"
                    series={[
                        { key: 'clicks', name: 'Clicks' },
                        { key: 'impressions', name: 'Impressions' },
                    ]}
                />
            </SectionCard>

            {breakdownsLoading && !breakdowns ? (
                <div className="grid gap-4 lg:grid-cols-2">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <Skeleton key={i} className="h-72 rounded-xl" />
                    ))}
                </div>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    <BreakdownTable
                        title="Queries"
                        rows={breakdowns?.gsc?.queries ?? []}
                        columns={[
                            ['query', 'Query'],
                            ['clicks', 'Clicks'],
                            ['impressions', 'Impressions'],
                            ['ctr', 'CTR'],
                        ]}
                    />
                    <BreakdownTable
                        title="Pages"
                        rows={breakdowns?.gsc?.pages ?? []}
                        columns={[
                            ['url', 'Page'],
                            ['clicks', 'Clicks'],
                            ['impressions', 'Impressions'],
                        ]}
                    />
                    <BreakdownTable
                        title="Countries"
                        rows={breakdowns?.gsc?.countries ?? []}
                        columns={[
                            ['country', 'Country'],
                            ['clicks', 'Clicks'],
                            ['impressions', 'Impressions'],
                        ]}
                    />
                    <SectionCard title="Devices (clicks)">
                        <CategoryPieChart data={breakdowns?.gsc?.devices ?? []} labelKey="device" valueKey="clicks" order={DEVICE_ORDER} />
                    </SectionCard>
                </div>
            )}

            {/* Per-site comparison */}
            <h3 className="text-muted-foreground mt-2 text-xs font-semibold tracking-wide uppercase">Website comparison</h3>
            <div className="grid gap-4 lg:grid-cols-2">
                <SectionCard title="GA4 users by website">
                    <CategoryBarChart data={comparisonChartRows} labelKey="name" valueKey="ga4_users" valueLabel="users" />
                </SectionCard>
                <SectionCard title="GSC clicks by website">
                    <CategoryBarChart data={comparisonChartRows} labelKey="name" valueKey="gsc_clicks" valueLabel="clicks" />
                </SectionCard>
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
                        {comparisonRows.map((row) => (
                            <tr key={row.website_id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                <td className="py-1.5">{row.name}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.ga4?.users)}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.ga4?.sessions)}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.gsc?.clicks)}</td>
                                <td className="py-1.5 text-right tabular-nums">{compact(row.gsc?.impressions)}</td>
                            </tr>
                        ))}
                        {comparisonRows.length === 0 && (
                            <tr>
                                <td colSpan={5} className="text-muted-foreground py-3 text-center">
                                    No mapped websites found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
