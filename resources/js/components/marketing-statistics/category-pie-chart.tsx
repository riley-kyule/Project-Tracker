import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { compactNumber as compact, useChartColors } from './chart-colors';

/** Pie/donut breakdown for a small, fixed set of categories (devices, etc). `order` pins colors to category identity so a filtered slice never repaints the survivors. */
export function CategoryPieChart({
    data,
    labelKey,
    valueKey,
    order,
}: {
    data: Record<string, unknown>[];
    labelKey: string;
    valueKey: string;
    order?: string[];
}) {
    const colors = useChartColors();

    if (data.length === 0) {
        return <p className="text-muted-foreground py-8 text-center text-sm">No data for this range.</p>;
    }

    const colorFor = (label: string, fallbackIndex: number) => {
        if (order) {
            const index = order.indexOf(label.toLowerCase());
            return colors.series[(index >= 0 ? index : order.length) % colors.series.length];
        }
        return colors.series[fallbackIndex % colors.series.length];
    };

    return (
        <ResponsiveContainer width="100%" height={260}>
            <PieChart>
                <Tooltip
                    content={({ active, payload }) =>
                        active && payload?.length ? (
                            <div className="bg-popover rounded-md border px-3 py-2 text-xs shadow-md">
                                <div className="flex items-center gap-2">
                                    <span className="inline-block h-0.5 w-3 shrink-0" style={{ backgroundColor: payload[0].payload.fill }} />
                                    <span className="font-semibold tabular-nums">{compact(payload[0].value as number)}</span>
                                    <span className="text-muted-foreground">{String(payload[0].name)}</span>
                                </div>
                            </div>
                        ) : null
                    }
                />
                <Legend wrapperStyle={{ fontSize: 12 }} formatter={(value) => <span className="text-secondary-foreground">{value}</span>} />
                <Pie
                    data={data}
                    dataKey={valueKey}
                    nameKey={labelKey}
                    cx="50%"
                    cy="50%"
                    outerRadius={90}
                    label={(entry: object) => {
                        const row = entry as Record<string, unknown>;
                        return `${row[labelKey]} ${(((row.percent as number) ?? 0) * 100).toFixed(0)}%`;
                    }}
                >
                    {data.map((row, i) => (
                        <Cell key={String(row[labelKey])} fill={colorFor(String(row[labelKey]), i)} />
                    ))}
                </Pie>
            </PieChart>
        </ResponsiveContainer>
    );
}
