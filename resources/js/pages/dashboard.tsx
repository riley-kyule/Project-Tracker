import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { statusLabels, statusVariants, type TicketStatus } from '@/pages/tickets/index';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

type DashTask = {
    id: number;
    task_number: number;
    title: string;
    priority: 'critical' | 'high' | 'medium' | 'low';
    due_at: string | null;
    board: { id: number; name: string };
    column?: { id: number; name: string; semantic_status: string };
    labels?: { id: number; name: string; color: string }[];
};

type DashTicket = {
    id: number;
    ticket_number: number;
    title: string;
    status: TicketStatus;
    category: { name: string };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const priorityColors: Record<DashTask['priority'], string> = {
    critical: 'text-red-600 dark:text-red-400',
    high: 'text-orange-600 dark:text-orange-400',
    medium: 'text-brand-600 dark:text-brand-400',
    low: 'text-muted-foreground',
};

function StatCard({ label, value, alert = false }: { label: string; value: number; alert?: boolean }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div className={`text-2xl font-semibold ${alert && value > 0 ? 'text-destructive' : ''}`}>{value}</div>
            <div className="text-muted-foreground text-sm">{label}</div>
        </div>
    );
}

export default function Dashboard({
    counts,
    myTasks,
    recentlyAssigned,
    myTickets,
}: {
    counts: {
        open: number;
        due_today: number;
        overdue: number;
        blocked: number;
        awaiting_review: number;
        completed_today: number;
        completed_total: number;
    };
    myTasks: DashTask[];
    recentlyAssigned: DashTask[];
    myTickets: DashTicket[];
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Welcome back, {auth.user.name.split(' ')[0]}</h1>

                <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-7">
                    <StatCard label="Open tasks" value={counts.open} />
                    <StatCard label="Due today" value={counts.due_today} alert />
                    <StatCard label="Overdue" value={counts.overdue} alert />
                    <StatCard label="Blocked" value={counts.blocked} alert />
                    <StatCard label="Awaiting review" value={counts.awaiting_review} />
                    <StatCard label="Completed today" value={counts.completed_today} />
                    <StatCard label="Completed tasks" value={counts.completed_total} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4 lg:col-span-2">
                        <h2 className="mb-2 text-sm font-semibold">My work</h2>
                        <ul className="divide-sidebar-border/40 dark:divide-sidebar-border/40 divide-y">
                            {myTasks.map((task) => {
                                const isOverdue = task.due_at !== null && new Date(task.due_at) < new Date();
                                return (
                                    <li key={task.id} className="flex flex-wrap items-center gap-2 py-2 text-sm">
                                        <span className="text-muted-foreground font-mono text-xs">T-{task.task_number}</span>
                                        <Link href={`/boards/${task.board.id}`} className="font-medium hover:underline">
                                            {task.title}
                                        </Link>
                                        {task.column && (
                                            <Badge variant={task.column.semantic_status === 'blocked' ? 'destructive' : 'secondary'}>
                                                {task.column.name}
                                            </Badge>
                                        )}
                                        <span className={`ml-auto text-xs font-medium capitalize ${priorityColors[task.priority]}`}>
                                            {task.priority}
                                        </span>
                                        {task.due_at && (
                                            <span className={`text-xs ${isOverdue ? 'text-destructive font-semibold' : 'text-muted-foreground'}`}>
                                                {new Date(task.due_at).toLocaleDateString()}
                                            </span>
                                        )}
                                    </li>
                                );
                            })}
                            {myTasks.length === 0 && <li className="text-muted-foreground py-2 text-sm">Nothing assigned to you. Enjoy it.</li>}
                        </ul>
                    </div>

                    <div className="flex flex-col gap-4">
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <h2 className="mb-2 text-sm font-semibold">Recently assigned</h2>
                            <ul className="space-y-1.5">
                                {recentlyAssigned.map((task) => (
                                    <li key={task.id} className="text-sm">
                                        <Link href={`/boards/${task.board.id}`} className="hover:underline">
                                            {task.title}
                                        </Link>
                                    </li>
                                ))}
                                {recentlyAssigned.length === 0 && <li className="text-muted-foreground text-sm">None yet.</li>}
                            </ul>
                        </div>
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <h2 className="mb-2 text-sm font-semibold">My open tickets</h2>
                            <ul className="space-y-1.5">
                                {myTickets.map((ticket) => (
                                    <li key={ticket.id} className="flex items-center gap-2 text-sm">
                                        <Link href={`/tickets/${ticket.id}`} className="hover:underline">
                                            TK-{ticket.ticket_number} {ticket.title}
                                        </Link>
                                        <Badge variant={statusVariants[ticket.status]} className="ml-auto">
                                            {statusLabels[ticket.status]}
                                        </Badge>
                                    </li>
                                ))}
                                {myTickets.length === 0 && <li className="text-muted-foreground text-sm">No open tickets.</li>}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
