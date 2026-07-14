import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { compactNumber as compact, useChartColors } from './chart-colors';

export function TrendChart({
    data,
    dateKey,
    series,
    valueFormat = compact,
}: {
    data: Record<string, unknown>[];
    dateKey: string;
    series: { key: string; name: string }[];
    /** Formats Y-axis ticks and tooltip values — defaults to compact numbers; pass a percent/decimal formatter for ratio metrics. */
    valueFormat?: (value: number) => string;
}) {
    const colors = useChartColors();
    const slots = colors.series;

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
                <YAxis
                    tick={{ fontSize: 12, fill: colors.muted }}
                    axisLine={false}
                    tickLine={false}
                    width={44}
                    tickFormatter={(value) => valueFormat(value as number)}
                />
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
                                        <span className="font-semibold tabular-nums">{valueFormat(entry.value as number)}</span>
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
