import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

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

export default function QueueHealth({
    failedJobs,
    failedJobsTotal,
    pendingByQueue,
}: {
    failedJobs: FailedJob[];
    failedJobsTotal: number;
    pendingByQueue: PendingQueue[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Queue Health" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Queue Health</h1>

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Pending jobs by queue</h2>
                    {pendingByQueue.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Nothing waiting — the queue is empty.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground text-left">
                                    <th className="p-2 font-medium">Queue</th>
                                    <th className="p-2 font-medium">Pending</th>
                                    <th className="p-2 font-medium">Oldest waiting since</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pendingByQueue.map((row) => (
                                    <tr key={row.queue} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                        <td className="p-2 font-mono text-xs">{row.queue}</td>
                                        <td className="p-2">{row.total}</td>
                                        <td className="p-2">{new Date(row.oldest_available_at).toLocaleString()}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
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
                                        <span className="text-muted-foreground ml-auto text-xs">{new Date(job.failed_at).toLocaleString()}</span>
                                    </div>
                                    <p className="text-muted-foreground mt-1 font-mono text-xs break-all">{job.exception}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
