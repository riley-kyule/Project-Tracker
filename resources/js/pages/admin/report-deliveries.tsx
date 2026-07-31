import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

type Delivery = {
    id: number;
    status: 'pending' | 'sent' | 'failed';
    queued_at: string | null;
    sent_at: string | null;
    failed_at: string | null;
    failure_reason: string | null;
    retry_count: number;
    recipient: { id: number; name: string } | null;
    snapshot: {
        report_date: string;
        report_type: string;
        department: { id: number; name: string } | null;
        user: { id: number; name: string } | null;
    } | null;
};

const ALL = 'all';

const statusVariant: Record<Delivery['status'], 'default' | 'destructive' | 'secondary'> = {
    sent: 'default',
    pending: 'secondary',
    failed: 'destructive',
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Report Deliveries', href: '/admin/report-deliveries' }];

function reportLabel(snapshot: Delivery['snapshot']): string {
    if (!snapshot) return 'Unknown report';
    if (snapshot.report_type === 'ceo_daily') return 'CEO daily summary';
    if (snapshot.report_type === 'department_daily') return `${snapshot.department?.name ?? 'Department'} daily summary`;
    if (snapshot.report_type === 'weekly_personal') return `${snapshot.user?.name ?? 'Personal'} weekly summary`;
    return snapshot.report_type;
}

export default function ReportDeliveries({
    deliveries,
    statuses,
    selected,
}: {
    deliveries: { data: Delivery[]; total: number };
    statuses: string[];
    selected: { status?: string };
}) {
    const applyStatus = (status: string) => {
        router.get('/admin/report-deliveries', status === ALL ? {} : { status }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Report Deliveries" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">Report Deliveries</h1>
                    <span className="text-muted-foreground text-sm">{deliveries.total} total</span>
                    <Select value={selected.status ?? ALL} onValueChange={applyStatus}>
                        <SelectTrigger className="ml-auto w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All statuses</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {status}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-sidebar-border/70 dark:border-sidebar-border border-b text-left">
                                <th className="p-3 font-medium">Report</th>
                                <th className="p-3 font-medium">Date</th>
                                <th className="p-3 font-medium">Recipient</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Sent</th>
                                <th className="p-3 font-medium">Retries</th>
                                <th className="p-3 font-medium">Failure reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            {deliveries.data.map((delivery) => (
                                <tr key={delivery.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className="p-3">{reportLabel(delivery.snapshot)}</td>
                                    <td className="p-3">{delivery.snapshot?.report_date}</td>
                                    <td className="p-3">{delivery.recipient?.name ?? '—'}</td>
                                    <td className="p-3">
                                        <Badge variant={statusVariant[delivery.status]}>{delivery.status}</Badge>
                                    </td>
                                    <td className="p-3">{delivery.sent_at ? new Date(delivery.sent_at).toLocaleString() : '—'}</td>
                                    <td className="p-3">{delivery.retry_count}</td>
                                    <td className="text-destructive p-3 text-xs">{delivery.failure_reason ?? ''}</td>
                                </tr>
                            ))}
                            {deliveries.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground p-6 text-center">
                                        No report deliveries yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
