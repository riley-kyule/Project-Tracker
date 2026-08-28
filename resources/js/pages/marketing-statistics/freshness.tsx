import { MarketingStatisticsShell } from '@/components/marketing-statistics/shell';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
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

function SourceCard({ title, source, websiteNames }: { title: string; source: SourceFreshness; websiteNames: Map<string, string> }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div className="mb-2 flex items-center justify-between">
                <h3 className="text-sm font-semibold">{title}</h3>
                <StatusBadge status={source.status} />
            </div>
            {source.error && (
                <p className="text-muted-foreground mb-2 text-xs" title={source.error}>
                    Couldn&apos;t check this source&apos;s freshness.
                </p>
            )}
            {source.sites.length > 0 && (
                <div className="overflow-x-auto">
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
                                    <td className="py-1.5">{websiteNames.get(site.website_id) ?? site.website_id}</td>
                                    <td className="py-1.5">{site.latest_date ?? '—'}</td>
                                    <td className="py-1.5 text-right tabular-nums">{site.days_behind ?? '—'}</td>
                                    <td className="py-1.5 text-right">
                                        <StatusBadge status={site.status} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function SourceCardSkeleton({ title }: { title: string }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div className="mb-2 flex items-center justify-between">
                <h3 className="text-sm font-semibold">{title}</h3>
                <Skeleton className="h-5 w-14 rounded-full" />
            </div>
            <div className="space-y-2">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-2/3" />
            </div>
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
    // website_id here is actually the domain (see AnalyticsFreshnessChecker),
    // same key the registry uses — look it up against the mapped sites so
    // the table shows the same human-readable name every other page does.
    const websiteNames = new Map(websites.map((website) => [website.website_id, website.name]));

    return (
        <MarketingStatisticsShell active="freshness" selected={selected} websites={websites} showDateRange={false} showComparison={false}>
            <Deferred
                data="sources"
                fallback={
                    <div className="grid gap-4 lg:grid-cols-3">
                        <SourceCardSkeleton title="GA4" />
                        <SourceCardSkeleton title="Google Search Console" />
                        <SourceCardSkeleton title="Ahrefs" />
                    </div>
                }
            >
                <>
                    {sources && (
                        <div className="grid gap-4 lg:grid-cols-3">
                            <SourceCard title="GA4" source={sources.ga4} websiteNames={websiteNames} />
                            <SourceCard title="Google Search Console" source={sources.gsc} websiteNames={websiteNames} />
                            <SourceCard title="Ahrefs" source={sources.ahrefs} websiteNames={websiteNames} />
                        </div>
                    )}
                </>
            </Deferred>
        </MarketingStatisticsShell>
    );
}
