import { SortableHeader, useClientSort } from '@/components/sortable-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { fmtDateTime } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePoll } from '@inertiajs/react';
import { useState } from 'react';

type FailedJob = {
    id: number;
    uuid: string;
    connection: string;
    queue: string;
    exception: string;
    failed_at: string;
};

type PendingQueue = {
    queue: string;
    total: number;
    oldest_available_at: string;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Queue Health', href: '/admin/queue-health' }];

const EXCEPTION_PREVIEW_LENGTH = 280;

function FailedJobException({ exception }: { exception: string }) {
    const [expanded, setExpanded] = useState(false);
    const isLong = exception.length > EXCEPTION_PREVIEW_LENGTH;

    return (
        <div>
            <p className={`text-muted-foreground mt-1 font-mono text-xs break-all ${expanded ? '' : 'line-clamp-3'}`}>{exception}</p>
            {isLong && (
                <button
                    type="button"
                    onClick={() => setExpanded((current) => !current)}
                    className="text-brand-600 dark:text-brand-400 mt-1 text-xs hover:underline"
                >
                    {expanded ? 'Show less' : 'Show more'}
                </button>
            )}
        </div>
    );
}

export default function QueueHealth({
    failedJobs,
    failedJobsTotal,
    pendingByQueue,
}: {
    failedJobs: FailedJob[];
    failedJobsTotal: number;
    pendingByQueue: PendingQueue[];
}) {
    // Keep this operational-health page reasonably fresh without requiring a manual refresh.
    usePoll(30000);

    const { sorted, sort, onSort } = useClientSort(pendingByQueue, (row, column) => {
        switch (column) {
            case 'queue':
                return row.queue;
            case 'total':
                return row.total;
            case 'oldest_available_at':
                return row.oldest_available_at;
            default:
                return null;
        }
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Queue Health" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <h1 className="text-xl font-semibold">Queue Health</h1>
                    <Button size="sm" variant="outline" className="ml-auto" onClick={() => router.reload()}>
                        Refresh
                    </Button>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Pending jobs by queue</h2>
                    {pendingByQueue.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Nothing waiting — the queue is empty.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground text-left">
                                        <SortableHeader column="queue" sort={sort} onSort={onSort} className="p-2">
                                            Queue
                                        </SortableHeader>
                                        <SortableHeader column="total" sort={sort} onSort={onSort} className="p-2">
                                            Pending
                                        </SortableHeader>
                                        <SortableHeader column="oldest_available_at" sort={sort} onSort={onSort} className="p-2">
                                            Oldest waiting since
                                        </SortableHeader>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sorted.map((row) => (
                                        <tr key={row.queue} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                            <td className="p-2 font-mono text-xs">{row.queue}</td>
                                            <td className="p-2">{row.total}</td>
                                            <td className="p-2">{fmtDateTime(row.oldest_available_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <div className="mb-2 flex items-center gap-2">
                        <h2 className="text-sm font-semibold">Failed jobs</h2>
                        <Badge variant={failedJobsTotal > 0 ? 'destructive' : 'secondary'}>{failedJobsTotal} total</Badge>
                    </div>
                    {failedJobs.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No failed jobs.</p>
                    ) : (
                        <ul className="divide-sidebar-border/40 dark:divide-sidebar-border/40 divide-y">
                            {failedJobs.map((job) => (
                                <li key={job.id} className="py-2 text-sm">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{job.queue}</span>
                                        <span className="text-muted-foreground text-xs">{job.connection}</span>
                                        <span className="text-muted-foreground ml-auto text-xs">{fmtDateTime(job.failed_at)}</span>
                                    </div>
                                    <FailedJobException exception={job.exception} />
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
