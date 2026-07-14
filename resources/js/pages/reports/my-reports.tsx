import { KpiTile } from '@/components/marketing-statistics/kpi-tile';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useSourceStatusToasts } from '@/hooks/use-source-status-toasts';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Kpi, type SourceStatus } from '@/types/marketing-statistics';
import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Reports', href: '/my-reports' }];

type AssignedWebsite = { id: number; name: string; domain: string | null; team: 'marketing' | 'customer_service' };
type ReportWebsite = { id: number; name: string; domain: string | null };
type SourceReport = { status: 'ok' | 'missing' | 'failed'; error: string | null; kpis: Record<string, Kpi> | null };

type MarketingSection = {
    websites: ReportWebsite[];
    error: string | null;
    ga4: SourceReport | null;
    gsc: SourceReport | null;
    ahrefs: SourceReport | null;
};

type CustomerServiceSection = {
    websites: ReportWebsite[];
    status: 'ok' | 'failed';
    error: string | null;
    data: Record<string, number> | null;
};

type Report = {
    period: { from: string; to: string };
    marketing: MarketingSection | null;
    customer_service: CustomerServiceSection | null;
};

const percent = (value: number) => `${(value * 100).toFixed(1)}%`;
const decimal = (value: number) => value.toFixed(1);
const whole = (value: number) => new Intl.NumberFormat('en').format(value);

function exportHref(websiteIds: number[], dateFrom: string, dateTo: string): string {
    const params = new URLSearchParams();
    websiteIds.forEach((id) => params.append('website_ids[]', String(id)));
    params.set('date_from', dateFrom);
    params.set('date_to', dateTo);

    return `/my-reports/export?${params.toString()}`;
}

export default function MyReports({
    assigned_websites,
    selected,
    report,
}: {
    assigned_websites: AssignedWebsite[];
    selected: { website_ids: number[]; date_from: string; date_to: string };
    report: Report | null;
}) {
    const [selectedIds, setSelectedIds] = useState<number[]>(selected.website_ids);
    const [dateFrom, setDateFrom] = useState(selected.date_from);
    const [dateTo, setDateTo] = useState(selected.date_to);

    const toggle = (id: number) => {
        setSelectedIds((ids) => (ids.includes(id) ? ids.filter((existing) => existing !== id) : [...ids, id]));
    };

    const generate = () => {
        router.get('/my-reports', { website_ids: selectedIds, date_from: dateFrom, date_to: dateTo }, { preserveState: true, preserveScroll: true });
    };

    const marketingSites = assigned_websites.filter((website) => website.team === 'marketing');
    const csSites = assigned_websites.filter((website) => website.team === 'customer_service');

    const marketing = report?.marketing;
    const marketingSources: Record<string, SourceStatus> | undefined =
        marketing && !marketing.error
            ? {
                  ga4: { status: marketing.ga4?.status ?? 'missing', error: marketing.ga4?.error ?? null },
                  gsc: { status: marketing.gsc?.status ?? 'missing', error: marketing.gsc?.error ?? null },
                  ahrefs: { status: marketing.ahrefs?.status ?? 'missing', error: marketing.ahrefs?.error ?? null },
              }
            : undefined;
    useSourceStatusToasts(marketingSources);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Reports" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">My Reports</h1>

                {assigned_websites.length === 0 ? (
                    <div className="text-muted-foreground rounded-xl border border-dashed p-6 text-sm">
                        You aren't assigned to any websites yet. Ask an administrator to add you as a member on the Websites page.
                    </div>
                ) : (
                    <>
                        <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-4 rounded-xl border p-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                {marketingSites.length > 0 && (
                                    <div className="space-y-2">
                                        <div className="text-sm font-medium">Marketing sites</div>
                                        {marketingSites.map((website) => (
                                            <label key={website.id} className="flex items-center gap-2 text-sm">
                                                <Checkbox checked={selectedIds.includes(website.id)} onCheckedChange={() => toggle(website.id)} />
                                                {website.name}
                                            </label>
                                        ))}
                                    </div>
                                )}
                                {csSites.length > 0 && (
                                    <div className="space-y-2">
                                        <div className="text-sm font-medium">Customer Service sites</div>
                                        {csSites.map((website) => (
                                            <label key={website.id} className="flex items-center gap-2 text-sm">
                                                <Checkbox checked={selectedIds.includes(website.id)} onCheckedChange={() => toggle(website.id)} />
                                                {website.name}
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-wrap items-end gap-2">
                                <div className="flex items-end gap-1">
                                    <input
                                        type="date"
                                        value={dateFrom}
                                        max={dateTo}
                                        onChange={(e) => setDateFrom(e.target.value)}
                                        className="border-input bg-background h-10 rounded-md border px-2 text-sm"
                                    />
                                    <span className="text-muted-foreground pb-2 text-sm">to</span>
                                    <input
                                        type="date"
                                        value={dateTo}
                                        min={dateFrom}
                                        onChange={(e) => setDateTo(e.target.value)}
                                        className="border-input bg-background h-10 rounded-md border px-2 text-sm"
                                    />
                                </div>
                                <div className="ml-auto flex gap-2">
                                    <Button onClick={generate} disabled={selectedIds.length === 0} type="button">
                                        Generate Report
                                    </Button>
                                    <Button variant="outline" disabled={selectedIds.length === 0} asChild>
                                        <a href={exportHref(selectedIds, dateFrom, dateTo)}>
                                            <Download className="mr-1 size-4" /> Export PDF
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </div>

                        {!report && (
                            <div className="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm">
                                Select at least one site and click Generate Report.
                            </div>
                        )}

                        {report?.marketing && (
                            <div className="space-y-3">
                                <h2 className="text-lg font-semibold">Marketing — {report.marketing.websites.map((w) => w.name).join(', ')}</h2>
                                {report.marketing.error ? (
                                    <div className="rounded-md border border-dashed border-amber-400/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                        {report.marketing.error}
                                    </div>
                                ) : (
                                    <>
                                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                            <KpiTile
                                                label="Aggregate Property Users"
                                                kpi={report.marketing.ga4?.kpis?.aggregate_property_users ?? null}
                                                format={whole}
                                            />
                                            <KpiTile label="Sessions" kpi={report.marketing.ga4?.kpis?.sessions ?? null} format={whole} />
                                            <KpiTile label="Key Events" kpi={report.marketing.ga4?.kpis?.key_events ?? null} format={whole} />
                                            <KpiTile
                                                label="Engagement Rate"
                                                kpi={report.marketing.ga4?.kpis?.engagement_rate ?? null}
                                                format={percent}
                                            />
                                            <KpiTile label="GSC Clicks" kpi={report.marketing.gsc?.kpis?.clicks ?? null} format={whole} />
                                            <KpiTile label="GSC Impressions" kpi={report.marketing.gsc?.kpis?.impressions ?? null} format={whole} />
                                            <KpiTile label="GSC CTR" kpi={report.marketing.gsc?.kpis?.ctr ?? null} format={percent} />
                                            <KpiTile
                                                label="Avg. Position"
                                                kpi={report.marketing.gsc?.kpis?.average_position ?? null}
                                                format={decimal}
                                            />
                                            {report.marketing.ahrefs?.status === 'ok' && (
                                                <>
                                                    <KpiTile
                                                        label="Domain Rating"
                                                        kpi={report.marketing.ahrefs.kpis?.domain_rating ?? null}
                                                        format={decimal}
                                                    />
                                                    <KpiTile label="Backlinks" kpi={report.marketing.ahrefs.kpis?.backlinks ?? null} format={whole} />
                                                    <KpiTile
                                                        label="Referring Domains"
                                                        kpi={report.marketing.ahrefs.kpis?.referring_domains ?? null}
                                                        format={whole}
                                                    />
                                                    <KpiTile
                                                        label="Estimated Organic Traffic"
                                                        kpi={report.marketing.ahrefs.kpis?.estimated_organic_traffic ?? null}
                                                        format={whole}
                                                    />
                                                </>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        )}

                        {report?.customer_service && (
                            <div className="space-y-3">
                                <h2 className="text-lg font-semibold">
                                    Customer Service — {report.customer_service.websites.map((w) => w.name).join(', ')}
                                </h2>
                                {report.customer_service.status === 'ok' && report.customer_service.data ? (
                                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                        {Object.entries(report.customer_service.data).map(([metric, value]) => (
                                            <div key={metric} className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                                                <div className="text-2xl font-semibold">{value}</div>
                                                <div className="text-muted-foreground text-sm capitalize">{metric.replace(/_/g, ' ')}</div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="rounded-md border border-dashed border-amber-400/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                        CRM data isn't available yet. {report.customer_service.error}
                                    </div>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
