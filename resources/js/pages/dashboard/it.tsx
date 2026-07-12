import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { statusLabels, statusVariants, type TicketStatus } from '@/pages/tickets/index';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type QueueTicket = {
    id: number;
    ticket_number: number;
    title: string;
    status: TicketStatus;
    priority: string;
    due_at: string | null;
    requester: { name: string };
    assignee: { name: string } | null;
    category: { name: string };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'IT Dashboard', href: '/dashboards/it' }];

function StatCard({ label, value, alert = false }: { label: string; value: number | string; alert?: boolean }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div className={`text-2xl font-semibold ${alert ? 'text-destructive' : ''}`}>{value}</div>
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

export default function ItDashboard({
    counts,
    averages,
    resolutionMethods,
    byCategory,
    queue,
}: {
    counts: { new: number; unassigned: number; critical: number; overdue: number; waiting: number; resolved_today: number };
    averages: { first_response_minutes: number | null; resolution_minutes: number | null };
    resolutionMethods: Record<string, number>;
    byCategory: Record<string, number>;
    queue: QueueTicket[];
}) {
    const remote = resolutionMethods.remote ?? 0;
    const physical = (resolutionMethods.office ?? 0) + (resolutionMethods.onsite ?? 0);
    const totalResolved = Object.values(resolutionMethods).reduce((sum, count) => sum + count, 0);
    const remoteShare = totalResolved > 0 ? Math.round((remote / totalResolved) * 100) : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="IT Dashboard" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">IT Dashboard</h1>

                <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <StatCard label="New" value={counts.new} alert={counts.new > 0} />
                    <StatCard label="Unassigned" value={counts.unassigned} alert={counts.unassigned > 0} />
                    <StatCard label="Critical open" value={counts.critical} alert={counts.critical > 0} />
                    <StatCard label="Overdue" value={counts.overdue} alert={counts.overdue > 0} />
                    <StatCard label="Waiting" value={counts.waiting} />
                    <StatCard label="Resolved today" value={counts.resolved_today} />
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="Avg first response (30 d)" value={formatMinutes(averages.first_response_minutes)} />
                    <StatCard label="Avg resolution (30 d)" value={formatMinutes(averages.resolution_minutes)} />
                    <StatCard label="Remote resolution share (30 d)" value={remoteShare === null ? '—' : `${remoteShare}%`} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                        <h2 className="mb-2 text-sm font-semibold">Resolution methods (last 30 days)</h2>
                        <ul className="space-y-1 text-sm">
                            <li className="flex justify-between">
                                <span>Remote</span>
                                <span className="font-medium">{remote}</span>
                            </li>
                            <li className="flex justify-between">
                                <span>Office / onsite</span>
                                <span className="font-medium">{physical}</span>
                            </li>
                            <li className="flex justify-between">
                                <span>Third party</span>
                                <span className="font-medium">{resolutionMethods.third_party ?? 0}</span>
                            </li>
                        </ul>
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                        <h2 className="mb-2 text-sm font-semibold">Open tickets by category</h2>
                        <ul className="space-y-1 text-sm">
                            {Object.entries(byCategory).map(([name, total]) => (
                                <li key={name} className="flex justify-between">
                                    <span>{name}</span>
                                    <span className="font-medium">{total}</span>
                                </li>
                            ))}
                            {Object.keys(byCategory).length === 0 && <li className="text-muted-foreground">No open tickets.</li>}
                        </ul>
                    </div>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <div className="p-4 pb-0">
                        <h2 className="text-sm font-semibold">Priority queue</h2>
                    </div>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground text-left">
                                <th className="p-3 font-medium">#</th>
                                <th className="p-3 font-medium">Title</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Priority</th>
                                <th className="p-3 font-medium">Assignee</th>
                                <th className="p-3 font-medium">Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            {queue.map((ticket) => (
                                <tr key={ticket.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                    <td className="p-3 font-mono text-xs">TK-{ticket.ticket_number}</td>
                                    <td className="p-3">
                                        <Link href={`/tickets/${ticket.id}`} className="text-brand-600 dark:text-brand-400 hover:underline">
                                            {ticket.title}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={statusVariants[ticket.status]}>{statusLabels[ticket.status]}</Badge>
                                    </td>
                                    <td className="p-3 capitalize">{ticket.priority}</td>
                                    <td className="p-3">{ticket.assignee?.name ?? '—'}</td>
                                    <td className="p-3">{ticket.due_at ? new Date(ticket.due_at).toLocaleString() : '—'}</td>
                                </tr>
                            ))}
                            {queue.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground border-t p-6 text-center">
                                        Queue is clear. 🎉
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
