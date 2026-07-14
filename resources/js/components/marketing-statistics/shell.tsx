import { FilterBar } from '@/components/marketing-statistics/filter-bar';
import { useSourceStatusToasts } from '@/hooks/use-source-status-toasts';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type MarketingFilters, type MarketingWebsite, type SourceStatus } from '@/types/marketing-statistics';
import { Head, Link } from '@inertiajs/react';

const TABS = [
    { key: 'overview', label: 'Overview', path: '/marketing-statistics' },
    { key: 'ga4', label: 'GA4', path: '/marketing-statistics/ga4' },
    { key: 'gsc', label: 'Google Search Console', path: '/marketing-statistics/gsc' },
    { key: 'ahrefs', label: 'Ahrefs', path: '/marketing-statistics/ahrefs' },
    { key: 'comparison', label: 'Website Comparison', path: '/marketing-statistics/comparison' },
    { key: 'freshness', label: 'Data Freshness', path: '/marketing-statistics/freshness' },
] as const;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Marketing Statistics', href: '/marketing-statistics' }];

export function buildFilterQuery(selected: MarketingFilters): string {
    const params: Record<string, string> = {
        website_id: selected.website_id,
        range: selected.range,
        comparison: selected.comparison,
    };

    if (selected.range === 'custom') {
        params.date_from = selected.date_from;
        params.date_to = selected.date_to;
    }

    if (selected.comparison === 'custom') {
        params.compare_from = selected.compare_from ?? '';
        params.compare_to = selected.compare_to ?? '';
    }

    const qs = new URLSearchParams(params).toString();

    return qs ? `?${qs}` : '';
}

export function MarketingStatisticsShell({
    active,
    selected,
    websites,
    sources,
    children,
}: {
    active: (typeof TABS)[number]['key'];
    selected: MarketingFilters;
    websites: MarketingWebsite[];
    sources?: Record<string, SourceStatus>;
    children: React.ReactNode;
}) {
    const query = buildFilterQuery(selected);
    const activeTab = TABS.find((tab) => tab.key === active)!;

    useSourceStatusToasts(sources);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Marketing Statistics — ${activeTab.label}`} />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Marketing Statistics</h1>

                <nav className="flex flex-wrap gap-1 border-b pb-2">
                    {TABS.map((tab) => (
                        <Link
                            key={tab.key}
                            href={`${tab.path}${query}`}
                            className={`rounded-md px-3 py-1.5 text-sm transition-colors ${
                                tab.key === active
                                    ? 'bg-secondary text-secondary-foreground font-medium'
                                    : 'text-muted-foreground hover:bg-secondary/50'
                            }`}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </nav>

                <FilterBar websites={websites} selected={selected} basePath={activeTab.path} />

                {children}
            </div>
        </AppLayout>
    );
}
