import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { statusLabels, statusVariants, type TicketStatus } from '@/pages/tickets/index';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

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

type WaitingOnMeTask = {
    id: number;
    task_number: number;
    title: string;
    board: { id: number; name: string };
    approval_note: string | null;
};

type Mention = {
    id: number;
    author: string;
    body: string;
    task_id: number;
    task_title: string;
    board_id: number;
    created_at: string;
};

type QuickCaptureBoard = { id: number; name: string; default_column_id: number };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

function QuickCaptureDialog({ boards, currentUserId }: { boards: QuickCaptureBoard[]; currentUserId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        title: '',
        board_id: boards[0]?.id.toString() ?? '',
        due_at: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const board = boards.find((b) => b.id.toString() === data.board_id);
        if (!board) return;

        transform((form) => ({
            title: form.title,
            board_column_id: board.default_column_id,
            priority: 'medium',
            due_at: form.due_at === '' ? undefined : form.due_at,
            primary_assignee_id: currentUserId,
        }));
        post(`/boards/${board.id}/tasks`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    if (boards.length === 0) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" className="ml-auto">
                    <Plus className="mr-1 size-4" /> Quick add task
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Quick add task</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="quick-title">Title</Label>
                        <Input id="quick-title" value={data.title} onChange={(e) => setData('title', e.target.value)} required autoFocus />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Board</Label>
                        <Select value={data.board_id} onValueChange={(value) => setData('board_id', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {boards.map((board) => (
                                    <SelectItem key={board.id} value={board.id.toString()}>
                                        {board.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="quick-due">Due date (optional)</Label>
                        <Input id="quick-due" type="date" value={data.due_at} onChange={(e) => setData('due_at', e.target.value)} className="w-40" />
                        <InputError message={errors.due_at} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Create task
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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
    waitingOnMe,
    mentions,
    quickCaptureBoards,
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
    waitingOnMe: WaitingOnMeTask[];
    mentions: Mention[];
    quickCaptureBoards: QuickCaptureBoard[];
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <h1 className="text-xl font-semibold">Welcome back, {auth.user.name.split(' ')[0]}</h1>
                    <QuickCaptureDialog boards={quickCaptureBoards} currentUserId={auth.user.id} />
                </div>

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
                            <h2 className="mb-2 text-sm font-semibold">Waiting on me</h2>
                            <ul className="space-y-1.5">
                                {waitingOnMe.map((task) => (
                                    <li key={task.id} className="text-sm">
                                        <Link href={`/boards/${task.board.id}`} className="hover:underline">
                                            T-{task.task_number} {task.title}
                                        </Link>
                                    </li>
                                ))}
                                {waitingOnMe.length === 0 && <li className="text-muted-foreground text-sm">Nothing waiting on your review.</li>}
                            </ul>
                        </div>
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <h2 className="mb-2 text-sm font-semibold">Mentions</h2>
                            <ul className="space-y-1.5">
                                {mentions.map((mention) => (
                                    <li key={mention.id} className="text-sm">
                                        <Link href={`/boards/${mention.board_id}`} className="hover:underline">
                                            <span className="font-medium">{mention.author}</span> on {mention.task_title}
                                        </Link>
                                        <p className="text-muted-foreground truncate text-xs">{mention.body}</p>
                                    </li>
                                ))}
                                {mentions.length === 0 && <li className="text-muted-foreground text-sm">No recent mentions.</li>}
                            </ul>
                        </div>
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
