import { useEffect, useState } from 'react';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

// Same validated categorical slots as the CEO dashboard's Traffic Data chart
// (scripts/validate_palette.js) — keep any new chart in this module on the
// same fixed order rather than introducing a second palette.
const SERIES = {
    light: { s1: '#2a78d6', s2: '#1baf7a', gridline: '#e1e0d9', muted: '#898781' },
    dark: { s1: '#3987e5', s2: '#199e70', gridline: '#2c2c2a', muted: '#898781' },
};

function useIsDark(): boolean {
    const [isDark, setIsDark] = useState(() => document.documentElement.classList.contains('dark'));

    useEffect(() => {
        const observer = new MutationObserver(() => setIsDark(document.documentElement.classList.contains('dark')));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    }, []);

    return isDark;
}

function compact(value: number): string {
    return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

export function TrendChart({ data, dateKey, series }: { data: Record<string, unknown>[]; dateKey: string; series: { key: string; name: string }[] }) {
    const isDark = useIsDark();
    const colors = isDark ? SERIES.dark : SERIES.light;
    const slots = [colors.s1, colors.s2];

    if (data.length === 0) {
        return <p className="text-muted-foreground py-8 text-center text-sm">No data for this range.</p>;
    }

    return (
        <ResponsiveContainer width="100%" height={260}>
            <LineChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <CartesianGrid stroke={colors.gridline} strokeDasharray="0" vertical={false} />
                <XAxis
                    dataKey={dateKey}
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
                                    {new Date(String(label)).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                                </div>
                                {payload.map((entry) => (
                                    <div key={entry.dataKey as string} className="flex items-center gap-2">
                                        <span className="inline-block h-0.5 w-3 shrink-0" style={{ backgroundColor: entry.color }} />
                                        <span className="font-semibold tabular-nums">{compact(entry.value as number)}</span>
                                        <span className="text-muted-foreground">{entry.name}</span>
                                    </div>
                                ))}
                            </div>
                        ) : null
                    }
                />
                <Legend wrapperStyle={{ fontSize: 12 }} formatter={(value) => <span className="text-secondary-foreground">{value}</span>} />
                {series.map((s, i) => (
                    <Line key={s.key} type="monotone" dataKey={s.key} name={s.name} stroke={slots[i % slots.length]} strokeWidth={2} dot={false} />
                ))}
            </LineChart>
        </ResponsiveContainer>
    );
}
