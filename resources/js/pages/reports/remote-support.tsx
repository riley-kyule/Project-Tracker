import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports/tasks' },
    { title: 'Remote support', href: '/reports/remote-support' },
];

const ALL = 'all';

function StatCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div className="text-2xl font-semibold">{value}</div>
            <div className="text-muted-foreground text-sm">{label}</div>
        </div>
    );
}

function formatMinutes(minutes: number | null) {
    if (minutes === null) return '—';
    if (minutes >= 1440) return `${(minutes / 1440).toFixed(1)} d`;
    if (minutes >= 60) return `${(minutes / 60).toFixed(1)} h`;
    return `${minutes} min`;
}

export default function RemoteSupportReport({
    totals,
    byMethod,
    departments,
    selected,
}: {
    totals: {
        resolved: number;
        avg_first_response_minutes: number | null;
        avg_resolution_minutes: number | null;
        avg_time_spent_minutes: number | null;
        reopen_rate: number | null;
    };
    byMethod: Record<string, number>;
    departments: { id: number; name: string }[];
    selected: { from: string; to: string; department_id?: string | number | null };
}) {
    const apply = (params: Record<string, string | undefined>) => {
        const merged = { from: selected.from, to: selected.to, department_id: selected.department_id?.toString(), ...params };
        router.get(
            '/reports/remote-support',
            Object.fromEntries(Object.entries(merged).filter(([, value]) => value && value !== ALL)) as Record<string, string>,
            { preserveState: true },
        );
    };

    const csvUrl = () => {
        const params = new URLSearchParams({ from: selected.from, to: selected.to, format: 'csv' });
        if (selected.department_id) params.set('department_id', selected.department_id.toString());
        return `/reports/remote-support?${params}`;
    };

    const remote = byMethod.remote ?? 0;
    const total = Object.values(byMethod).reduce((sum, count) => sum + count, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Remote support report" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">Remote support report</h1>
                    <Link href="/reports/tasks" className="text-brand-600 dark:text-brand-400 text-sm hover:underline">
                        Task report →
                    </Link>
                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        <Input type="date" value={selected.from} onChange={(e) => apply({ from: e.target.value })} className="w-40" />
                        <Input type="date" value={selected.to} onChange={(e) => apply({ to: e.target.value })} className="w-40" />
                        <Select value={selected.department_id?.toString() ?? ALL} onValueChange={(value) => apply({ department_id: value })}>
                            <SelectTrigger className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All departments</SelectItem>
                                {departments.map((department) => (
                                    <SelectItem key={department.id} value={department.id.toString()}>
                                        {department.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button asChild size="sm" variant="secondary">
                            <a href={csvUrl()}>
                                <Download className="mr-1 size-4" /> CSV
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <StatCard label="Tickets resolved" value={totals.resolved.toString()} />
                    <StatCard label="Avg first response" value={formatMinutes(totals.avg_first_response_minutes)} />
                    <StatCard label="Avg resolution" value={formatMinutes(totals.avg_resolution_minutes)} />
                    <StatCard label="Avg time spent" value={formatMinutes(totals.avg_time_spent_minutes)} />
                    <StatCard label="Reopen rate" value={totals.reopen_rate === null ? '—' : `${totals.reopen_rate}%`} />
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border max-w-xl rounded-xl border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Resolution methods</h2>
                    {total === 0 && <p className="text-muted-foreground text-sm">No resolved tickets in this period.</p>}
                    <ul className="space-y-2">
                        {Object.entries(byMethod).map(([method, count]) => (
                            <li key={method} className="flex items-center gap-2 text-sm">
                                <span className="w-28 capitalize">{method.replace('_', ' ')}</span>
                                <div className="bg-secondary h-3 flex-1 overflow-hidden rounded-full">
                                    <div
                                        className={method === 'remote' ? 'bg-brand-500 h-full' : 'bg-brand-800 h-full'}
                                        style={{ width: `${(count / total) * 100}%` }}
                                    />
                                </div>
                                <span className="w-14 text-right font-medium">
                                    {count} ({Math.round((count / total) * 100)}%)
                                </span>
                            </li>
                        ))}
                    </ul>
                    {total > 0 && (
                        <p className="text-muted-foreground mt-3 text-xs">
                            {Math.round((remote / total) * 100)}% of support in this period was resolved fully remotely.
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
