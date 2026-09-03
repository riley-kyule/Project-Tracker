import { Pagination, type Paginated } from '@/components/pagination';
import { SortableHeader, type SortState } from '@/components/sortable-header';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { fmtDateTime } from '@/lib/utils';
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

const statusLabels: Record<Delivery['status'], string> = {
    pending: 'Pending',
    sent: 'Sent',
    failed: 'Failed',
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'System Report Log', href: '/admin/report-deliveries' }];

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
    sort: sortColumn,
    direction,
}: {
    deliveries: Paginated<Delivery>;
    statuses: string[];
    selected: { status?: string };
    sort: string | null;
    direction: 'asc' | 'desc';
}) {
    const sort: SortState = { column: sortColumn, direction };

    const applyStatus = (status: string) => {
        router.get(
            '/admin/report-deliveries',
            { status: status === ALL ? undefined : status, sort: sortColumn ?? undefined, direction },
            { preserveState: true, preserveScroll: true },
        );
    };

    const onSort = (column: string) => {
        const nextDirection = sort.column === column && sort.direction === 'asc' ? 'desc' : 'asc';
        router.get(
            '/admin/report-deliveries',
            { status: selected.status, sort: column, direction: nextDirection },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="System Report Log" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">System Report Log</h1>
                    <span className="text-muted-foreground text-sm">{deliveries.total} total</span>
                    <Select value={selected.status ?? ALL} onValueChange={applyStatus}>
                        <SelectTrigger className="ml-auto w-40" aria-label="Filter by status">
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
                                <SortableHeader column="status" sort={sort} onSort={onSort} className="p-3">
                                    Status
                                </SortableHeader>
                                <SortableHeader column="sent_at" sort={sort} onSort={onSort} className="p-3">
                                    Sent
                                </SortableHeader>
                                <SortableHeader column="retry_count" sort={sort} onSort={onSort} className="p-3">
                                    Retries
                                </SortableHeader>
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
                                        <Badge variant={statusVariant[delivery.status]}>{statusLabels[delivery.status]}</Badge>
                                    </td>
                                    <td className="p-3">{fmtDateTime(delivery.sent_at)}</td>
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
                <Pagination meta={deliveries} />
            </div>
        </AppLayout>
    );
}
