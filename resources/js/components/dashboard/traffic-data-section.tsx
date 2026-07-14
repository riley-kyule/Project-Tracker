import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useEffect, useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

function TrafficDataSkeleton() {
    return (
        <div className="flex flex-col gap-4">
            <Skeleton className="h-5 w-28" />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Skeleton key={i} className="h-24 rounded-xl" />
                ))}
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Skeleton key={i} className="h-72 rounded-xl" />
                ))}
            </div>
        </div>
    );
}

type Website = { website_domain: string; website_name: string; country: string | null };
type Summary = { users: number; sessions: number; key_events: number; engagement_rate: number | null };
type TrendPoint = { event_date: string; users: number; sessions: number };
type TrafficSource = { source: string; medium: string; users: number };
type Device = { device_category: string; users: number };
type LandingPage = { page_location: string; users: number; page_views: number };
type VisitorLocation = { user_country: string; users: number };

type TrafficResponse = {
    configured: boolean;
    error?: string;
    summary?: { current: Summary; comparison: Summary | null };
    trend?: TrendPoint[];
    trafficSources?: TrafficSource[];
    devices?: Device[];
    landingPages?: LandingPage[];
    locations?: VisitorLocation[];
};

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

// Fixed order, validated against this app's light/dark chart surfaces
// (scripts/validate_palette.js) — assign by series identity, never cycle.
const SERIES = {
    light: { s1: '#2a78d6', s2: '#1baf7a', s3: '#eda100', s4: '#008300', gridline: '#e1e0d9', muted: '#898781' },
    dark: { s1: '#3987e5', s2: '#199e70', s3: '#c98500', s4: '#008300', gridline: '#2c2c2a', muted: '#898781' },
};

const DEVICE_ORDER = ['desktop', 'mobile', 'tablet'];

function useIsDark(): boolean {
    const [isDark, setIsDark] = useState(() => document.documentElement.classList.contains('dark'));

    useEffect(() => {
        const observer = new MutationObserver(() => setIsDark(document.documentElement.classList.contains('dark')));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    }, []);

    return isDark;
}

function toDateInput(date: Date): string {
    return date.toISOString().slice(0, 10);
}

function compact(value: number): string {
    return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

function pct(value: number | null): string {
    return value === null ? '—' : `${(value * 100).toFixed(1)}%`;
}

function StatTile({
    label,
    value,
    comparisonValue,
    formatValue,
}: {
    label: string;
    value: number;
    comparisonValue: number | null | undefined;
    formatValue: (n: number) => string;
}) {
    const delta =
        comparisonValue !== null && comparisonValue !== undefined && comparisonValue !== 0 ? (value - comparisonValue) / comparisonValue : null;

    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div className="text-2xl font-semibold">{formatValue(value)}</div>
            <div className="text-muted-foreground text-sm">{label}</div>
            {delta !== null && (
                <div className={`mt-1 text-xs font-medium ${delta >= 0 ? 'text-[#006300] dark:text-[#0ca30c]' : 'text-destructive'}`}>
                    {delta >= 0 ? '▲' : '▼'} {Math.abs(delta * 100).toFixed(1)}% vs comparison period
                </div>
            )}
        </div>
    );
}

function TooltipRow({ color, name, value }: { color: string; name: string; value: string }) {
    return (
        <div className="flex items-center gap-2">
            <span className="inline-block h-0.5 w-3 shrink-0" style={{ backgroundColor: color }} />
            <span className="font-semibold tabular-nums">{value}</span>
            <span className="text-muted-foreground">{name}</span>
        </div>
    );
}

function ChartCard({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            {children}
        </div>
    );
}

export function TrafficDataSection() {
    const isDark = useIsDark();
    const colors = isDark ? SERIES.dark : SERIES.light;

    const [configured, setConfigured] = useState<boolean | null>(null);
    const [websites, setWebsites] = useState<Website[]>([]);
    const [websitesError, setWebsitesError] = useState<string | null>(null);
    const [websiteDomain, setWebsiteDomain] = useState<string>('');

    const [datePreset, setDatePreset] = useState<(typeof DATE_PRESETS)[number]['key']>('last_30_days');
    const [customFrom, setCustomFrom] = useState(() => toDateInput(new Date(Date.now() - 30 * 86400000)));
    const [customTo, setCustomTo] = useState(() => toDateInput(new Date(Date.now() - 86400000)));
    const [comparisonPeriod, setComparisonPeriod] = useState<(typeof COMPARISON_OPTIONS)[number]['value']>('previous_period');

    const [data, setData] = useState<TrafficResponse | null>(null);
    const [loading, setLoading] = useState(false);

    const { dateFrom, dateTo } = useMemo(() => {
        const preset = DATE_PRESETS.find((p) => p.key === datePreset);

        if (!preset || preset.days === null) {
            return { dateFrom: customFrom, dateTo: customTo };
        }

        const to = new Date(Date.now() - 86400000);
        const from = new Date(to.getTime() - (preset.days - 1) * 86400000);

        return { dateFrom: toDateInput(from), dateTo: toDateInput(to) };
    }, [datePreset, customFrom, customTo]);

    useEffect(() => {
        fetch('/dashboards/ceo/traffic-data/websites', { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((payload: { configured: boolean; websites: Website[]; error?: string }) => {
                setConfigured(payload.configured);
                setWebsites(payload.websites);
                setWebsitesError(payload.error ?? null);
                if (payload.websites.length > 0) {
                    setWebsiteDomain(payload.websites[0].website_domain);
                }
            })
            .catch(() => setConfigured(false));
    }, []);

    useEffect(() => {
        if (!configured || !websiteDomain) {
            return;
        }

        setLoading(true);
        const params = new URLSearchParams({
            website_domain: websiteDomain,
            date_from: dateFrom,
            date_to: dateTo,
            comparison_period: comparisonPeriod,
        });

        fetch(`/dashboards/ceo/traffic-data?${params}`, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((payload: TrafficResponse) => setData(payload))
            .catch(() => setData({ configured: true, error: 'Could not load traffic data.' }))
            .finally(() => setLoading(false));
    }, [configured, websiteDomain, dateFrom, dateTo, comparisonPeriod]);

    if (configured === null) {
        return <TrafficDataSkeleton />;
    }

    if (!configured) {
        return (
            <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <h2 className="mb-1 text-sm font-semibold">Traffic data</h2>
                <p className="text-muted-foreground text-sm">
                    BigQuery analytics isn't connected yet. See <code className="font-mono">ANALYTICS_BIGQUERY_FINDINGS.md</code> for setup steps.
                </p>
            </div>
        );
    }

    if (websites.length === 0) {
        return (
            <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                <h2 className="mb-1 text-sm font-semibold">Traffic data</h2>
                <p className="text-muted-foreground text-sm">
                    {websitesError
                        ? 'Connected, but the reporting views could not be queried — see the authorized-view setup notes.'
                        : 'No websites have recent data in the reporting views yet.'}
                </p>
                {websitesError && <p className="text-muted-foreground mt-1 font-mono text-xs">{websitesError}</p>}
            </div>
        );
    }

    const current = data?.summary?.current;
    const comparison = data?.summary?.comparison;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold">Traffic data</h2>
                {loading && <span className="text-muted-foreground text-xs">Refreshing…</span>}
            </div>

            {/* Filters: one row, above every chart below — they all scope to the same slice. */}
            <div className="flex flex-wrap items-center gap-2">
                <Select value={websiteDomain} onValueChange={setWebsiteDomain}>
                    <SelectTrigger className="w-56">
                        <SelectValue placeholder="Select a website" />
                    </SelectTrigger>
                    <SelectContent>
                        {websites.map((website) => (
                            <SelectItem key={website.website_domain} value={website.website_domain}>
                                {website.website_name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={datePreset} onValueChange={(value) => setDatePreset(value as typeof datePreset)}>
                    <SelectTrigger className="w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {DATE_PRESETS.map((preset) => (
                            <SelectItem key={preset.key} value={preset.key}>
                                {preset.label}
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

            {data?.error && (
                <p className="text-muted-foreground rounded-md border border-dashed p-3 text-sm">
                    Connected, but this query didn't return data — the reporting views may not be queryable yet for this project (see the
                    authorized-view notes), or this website/range has no rows.
                    <span className="mt-1 block font-mono text-xs">{data.error}</span>
                </p>
            )}

            {!current && loading && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <Skeleton key={i} className="h-24 rounded-xl" />
                    ))}
                </div>
            )}

            {current && (
                <>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <StatTile label="Users" value={current.users} comparisonValue={comparison?.users} formatValue={compact} />
                        <StatTile label="Sessions" value={current.sessions} comparisonValue={comparison?.sessions} formatValue={compact} />
                        <StatTile label="Key events" value={current.key_events} comparisonValue={comparison?.key_events} formatValue={compact} />
                        <StatTile
                            label="Engagement rate"
                            value={current.engagement_rate ?? 0}
                            comparisonValue={comparison?.engagement_rate}
                            formatValue={(v) => pct(v)}
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <ChartCard title="Users & sessions trend">
                            <ResponsiveContainer width="100%" height={260}>
                                <LineChart data={data?.trend ?? []} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                    <CartesianGrid stroke={colors.gridline} strokeDasharray="0" vertical={false} />
                                    <XAxis
                                        dataKey="event_date"
                                        tick={{ fontSize: 12, fill: colors.muted }}
                                        tickFormatter={(value) => new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                                        axisLine={{ stroke: colors.gridline }}
                                        tickLine={false}
                                    />
                                    <YAxis tick={{ fontSize: 12, fill: colors.muted }} axisLine={false} tickLine={false} width={44} />
                                    <Tooltip
                                        content={({ active, payload, label }) =>
                                            active && payload?.length ? (
                                                <div className="bg-popover rounded-md border px-3 py-2 text-xs shadow-md">
                                                    <div className="text-muted-foreground mb-1">
                                                        {new Date(String(label)).toLocaleDateString(undefined, {
                                                            month: 'short',
                                                            day: 'numeric',
                                                            year: 'numeric',
                                                        })}
                                                    </div>
                                                    {payload.map((entry) => (
                                                        <TooltipRow
                                                            key={entry.dataKey as string}
                                                            color={entry.color ?? colors.s1}
                                                            name={entry.name as string}
                                                            value={compact(entry.value as number)}
                                                        />
                                                    ))}
                                                </div>
                                            ) : null
                                        }
                                    />
                                    <Legend
                                        wrapperStyle={{ fontSize: 12 }}
                                        formatter={(value) => <span className="text-secondary-foreground">{value}</span>}
                                    />
                                    <Line type="monotone" dataKey="users" name="Users" stroke={colors.s1} strokeWidth={2} dot={false} />
                                    <Line type="monotone" dataKey="sessions" name="Sessions" stroke={colors.s2} strokeWidth={2} dot={false} />
                                </LineChart>
                            </ResponsiveContainer>
                        </ChartCard>

                        <ChartCard title="Traffic sources (users)">
                            <ResponsiveContainer width="100%" height={260}>
                                <BarChart data={data?.trafficSources ?? []} layout="vertical" margin={{ top: 8, right: 24, left: 8, bottom: 0 }}>
                                    <CartesianGrid stroke={colors.gridline} horizontal={false} />
                                    <XAxis type="number" tick={{ fontSize: 12, fill: colors.muted }} axisLine={false} tickLine={false} />
                                    <YAxis
                                        type="category"
                                        dataKey="source"
                                        tick={{ fontSize: 12, fill: colors.muted }}
                                        axisLine={false}
                                        tickLine={false}
                                        width={110}
                                    />
                                    <Tooltip
                                        content={({ active, payload }) =>
                                            active && payload?.length ? (
                                                <div className="bg-popover rounded-md border px-3 py-2 text-xs shadow-md">
                                                    <TooltipRow
                                                        color={colors.s1}
                                                        name={`${payload[0].payload.source} / ${payload[0].payload.medium}`}
                                                        value={`${compact(payload[0].value as number)} users`}
                                                    />
                                                </div>
                                            ) : null
                                        }
                                    />
                                    <Bar dataKey="users" name="Users" fill={colors.s1} radius={[0, 4, 4, 0]} barSize={20} />
                                </BarChart>
                            </ResponsiveContainer>
                        </ChartCard>

                        <ChartCard title="Devices (users)">
                            <ResponsiveContainer width="100%" height={260}>
                                <PieChart>
                                    <Tooltip
                                        content={({ active, payload }) =>
                                            active && payload?.length ? (
                                                <div className="bg-popover rounded-md border px-3 py-2 text-xs shadow-md">
                                                    <TooltipRow
                                                        color={payload[0].payload.fill}
                                                        name={String(payload[0].name)}
                                                        value={`${compact(payload[0].value as number)} users`}
                                                    />
                                                </div>
                                            ) : null
                                        }
                                    />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Pie
                                        data={data?.devices ?? []}
                                        dataKey="users"
                                        nameKey="device_category"
                                        cx="50%"
                                        cy="50%"
                                        outerRadius={90}
                                        label={(entry: { device_category?: string; percent?: number }) =>
                                            `${entry.device_category} ${((entry.percent ?? 0) * 100).toFixed(0)}%`
                                        }
                                    >
                                        {(data?.devices ?? []).map((device) => {
                                            const orderIndex = DEVICE_ORDER.indexOf(device.device_category?.toLowerCase());
                                            const slot = [colors.s1, colors.s2, colors.s3, colors.s4][orderIndex >= 0 ? orderIndex : 3];
                                            return <Cell key={device.device_category} fill={slot} />;
                                        })}
                                    </Pie>
                                </PieChart>
                            </ResponsiveContainer>
                        </ChartCard>

                        <ChartCard title="Top landing pages">
                            <div className="max-h-[260px] overflow-y-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-muted-foreground text-left">
                                            <th className="py-1.5 font-medium">Page</th>
                                            <th className="py-1.5 text-right font-medium">Users</th>
                                            <th className="py-1.5 text-right font-medium">Page views</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(data?.landingPages ?? []).map((page) => (
                                            <tr key={page.page_location} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                                <td className="max-w-56 truncate py-1.5" title={page.page_location}>
                                                    {page.page_location}
                                                </td>
                                                <td className="py-1.5 text-right tabular-nums">{page.users.toLocaleString()}</td>
                                                <td className="py-1.5 text-right tabular-nums">{page.page_views.toLocaleString()}</td>
                                            </tr>
                                        ))}
                                        {(data?.landingPages ?? []).length === 0 && (
                                            <tr>
                                                <td colSpan={3} className="text-muted-foreground py-3 text-center">
                                                    No data for this range.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </ChartCard>
                    </div>

                    <ChartCard title="Top visitor countries">
                        <div className="max-h-64 overflow-y-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground text-left">
                                        <th className="py-1.5 font-medium">Country</th>
                                        <th className="py-1.5 text-right font-medium">Users</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(data?.locations ?? []).map((location) => (
                                        <tr key={location.user_country} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                            <td className="py-1.5">{location.user_country}</td>
                                            <td className="py-1.5 text-right tabular-nums">{location.users.toLocaleString()}</td>
                                        </tr>
                                    ))}
                                    {(data?.locations ?? []).length === 0 && (
                                        <tr>
                                            <td colSpan={2} className="text-muted-foreground py-3 text-center">
                                                No data for this range.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </ChartCard>
                </>
            )}
        </div>
    );
}
