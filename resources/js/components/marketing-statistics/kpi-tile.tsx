import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { type Kpi } from '@/types/marketing-statistics';
import { Link } from '@inertiajs/react';

function compact(value: number): string {
    return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

function relativeTime(iso: string | null): string | null {
    if (!iso) return null;

    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (minutes < 1440) return `${Math.round(minutes / 60)}h ago`;
    return `${Math.round(minutes / 1440)}d ago`;
}

export function KpiTile({
    label,
    kpi,
    format = compact,
    href,
    drilldown,
    drilldownTitle,
}: {
    label: string;
    kpi: Kpi | null;
    format?: (value: number) => string;
    href?: string;
    /** In-depth breakdown content (a chart, usually) shown in a dialog on click — built from data already on the page, so it opens instantly. */
    drilldown?: React.ReactNode;
    drilldownTitle?: string;
}) {
    const inner = (
        <div className="border-sidebar-border/70 dark:border-sidebar-border hover:border-brand-500 h-full rounded-xl border p-4 transition-colors">
            <div className="text-2xl font-semibold">{kpi?.current !== null && kpi?.current !== undefined ? format(kpi.current) : '—'}</div>
            <div className="text-muted-foreground text-sm">{label}</div>
            {kpi?.percentage_change !== null && kpi?.percentage_change !== undefined && (
                <div className={`mt-1 text-xs font-medium ${kpi.percentage_change >= 0 ? 'text-[#006300] dark:text-[#0ca30c]' : 'text-destructive'}`}>
                    {kpi.percentage_change >= 0 ? '▲' : '▼'} {Math.abs(kpi.percentage_change * 100).toFixed(1)}% vs comparison
                </div>
            )}
            {kpi && (
                <div className="mt-2 flex items-center gap-1.5">
                    <Badge variant="outline" className="text-[10px] uppercase">
                        {kpi.data_source}
                    </Badge>
                    {relativeTime(kpi.last_updated) && <span className="text-muted-foreground text-[10px]">{relativeTime(kpi.last_updated)}</span>}
                </div>
            )}
        </div>
    );

    if (drilldown) {
        return (
            <Dialog>
                <DialogTrigger asChild>
                    <button type="button" className="block h-full w-full text-left">
                        {inner}
                    </button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{drilldownTitle ?? label}</DialogTitle>
                    </DialogHeader>
                    {drilldown}
                </DialogContent>
            </Dialog>
        );
    }

    return href ? <Link href={href}>{inner}</Link> : inner;
}
