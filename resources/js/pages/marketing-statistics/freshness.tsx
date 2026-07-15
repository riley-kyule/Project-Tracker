import { MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { Badge } from '@/components/ui/badge';
import { type MarketingFilters, type MarketingWebsite } from '@/types/marketing-statistics';
import { Deferred } from '@inertiajs/react';

type SiteFreshness = { website_id: string; latest_date: string | null; days_behind: number | null; status: string };

type SourceFreshness = { status: string; error: string | null; sites: SiteFreshness[] };

const STATUS_VARIANT: Record<string, 'secondary' | 'destructive' | 'outline'> = {
    ok: 'secondary',
    missing: 'outline',
    delayed: 'destructive',
    failed: 'destructive',
};

function StatusBadge({ status }: { status: string }) {
    return <Badge variant={STATUS_VARIANT[status] ?? 'outline'}>{status}</Badge>;
}

function SourceCard({ title, source }: { title: string; source: SourceFreshness }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div className="mb-2 flex items-center justify-between">
                <h3 className="text-sm font-semibold">{title}</h3>
                <StatusBadge status={source.status} />
            </div>
            {source.error && <p className="text-muted-foreground mb-2 font-mono text-xs">{source.error}</p>}
            {source.sites.length > 0 && (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-muted-foreground text-left">
                            <th className="py-1.5 font-medium">Website</th>
                            <th className="py-1.5 font-medium">Latest data</th>
                            <th className="py-1.5 text-right font-medium">Days behind</th>
                            <th className="py-1.5 text-right font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {source.sites.map((site) => (
                            <tr key={site.website_id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                <td className="py-1.5">{site.website_id}</td>
                                <td className="py-1.5">{site.latest_date ?? '—'}</td>
                                <td className="py-1.5 text-right tabular-nums">{site.days_behind ?? '—'}</td>
                                <td className="py-1.5 text-right">
                                    <StatusBadge status={site.status} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}

export default function DataFreshness({
    selected,
    websites,
    sources,
}: {
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    sources?: { ga4: SourceFreshness; gsc: SourceFreshness; ahrefs: SourceFreshness };
}) {
    return (
        <MarketingStatisticsShell active="freshness" selected={selected} websites={websites}>
            <Deferred data="sources" fallback={<></>}>
                <>
                    {sources && (
                        <div className="grid gap-4 lg:grid-cols-3">
                            <SourceCard title="GA4" source={sources.ga4} />
                            <SourceCard title="Google Search Console" source={sources.gsc} />
                            <SourceCard title="Ahrefs" source={sources.ahrefs} />
                        </div>
                    )}
                </>
            </Deferred>
        </MarketingStatisticsShell>
    );
}
