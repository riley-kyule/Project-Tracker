import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { compactNumber as compact, useChartColors } from './chart-colors';

/** Horizontal single-series bar chart for a small set of ranked categories (top sources, per-website comparisons, etc). */
export function CategoryBarChart({
    data,
    labelKey,
    valueKey,
    valueLabel = 'Value',
    tooltipLabel,
}: {
    data: Record<string, unknown>[];
    labelKey: string;
    valueKey: string;
    valueLabel?: string;
    tooltipLabel?: (row: Record<string, unknown>) => string;
}) {
    const colors = useChartColors();

    if (data.length === 0) {
        return <p className="text-muted-foreground py-8 text-center text-sm">No data for this range.</p>;
    }

    // Scales with row count so long comparisons (e.g. all mapped websites)
    // stay legible rather than cramming every bar into a fixed 260px.
    const height = Math.min(520, Math.max(260, data.length * 32));

    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={data} layout="vertical" margin={{ top: 8, right: 24, left: 8, bottom: 0 }}>
                <CartesianGrid stroke={colors.gridline} horizontal={false} />
                <XAxis type="number" tick={{ fontSize: 12, fill: colors.muted }} axisLine={false} tickLine={false} />
                <YAxis type="category" dataKey={labelKey} tick={{ fontSize: 12, fill: colors.muted }} axisLine={false} tickLine={false} width={110} />
                <Tooltip
                    content={({ active, payload }) =>
                        active && payload?.length ? (
                            <div className="bg-popover rounded-md border px-3 py-2 text-xs shadow-md">
                                <div className="flex items-center gap-2">
                                    <span className="inline-block h-0.5 w-3 shrink-0" style={{ backgroundColor: colors.series[0] }} />
                                    <span className="font-semibold tabular-nums">{compact(payload[0].value as number)}</span>
                                    <span className="text-muted-foreground">
                                        {tooltipLabel ? tooltipLabel(payload[0].payload as Record<string, unknown>) : valueLabel}
                                    </span>
                                </div>
                            </div>
                        ) : null
                    }
                />
                <Bar dataKey={valueKey} name={valueLabel} fill={colors.series[0]} radius={[0, 4, 4, 0]} barSize={20} />
            </BarChart>
        </ResponsiveContainer>
    );
}
