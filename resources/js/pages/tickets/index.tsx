import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

export type TicketStatus =
    | 'new'
    | 'assigned'
    | 'in_progress'
    | 'waiting_user'
    | 'waiting_third_party'
    | 'escalated'
    | 'resolved'
    | 'closed'
    | 'reopened';

type TicketRow = {
    id: number;
    ticket_number: number;
    title: string;
    status: TicketStatus;
    priority: 'critical' | 'high' | 'medium' | 'low';
    requester: { id: number; name: string };
    assignee: { id: number; name: string } | null;
    category: { id: number; name: string };
    due_at: string | null;
    created_at: string;
};

type Category = { id: number; name: string };
type Person = { id: number; name: string };

export const statusLabels: Record<TicketStatus, string> = {
    new: 'New',
    assigned: 'Assigned',
    in_progress: 'In Progress',
    waiting_user: 'Waiting for User',
    waiting_third_party: 'Waiting for Third Party',
    escalated: 'Escalated',
    resolved: 'Resolved',
    closed: 'Closed',
    reopened: 'Reopened',
};

export const statusVariants: Record<TicketStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    new: 'destructive',
    assigned: 'default',
    in_progress: 'default',
    waiting_user: 'secondary',
    waiting_third_party: 'secondary',
    escalated: 'destructive',
    resolved: 'outline',
    closed: 'outline',
    reopened: 'destructive',
};

const priorityColors: Record<TicketRow['priority'], string> = {
    critical: 'text-red-600 dark:text-red-400',
    high: 'text-orange-600 dark:text-orange-400',
    medium: 'text-brand-600 dark:text-brand-400',
    low: 'text-muted-foreground',
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Service Desk', href: '/tickets' }];

const ALL = 'all';

function NewTicketDialog({ categories, canCreateForOthers, users }: { categories: Category[]; canCreateForOthers: boolean; users: Person[] }) {
    const [open, setOpen] = useState(false);
    const [onBehalf, setOnBehalf] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        description: '',
        category_id: '',
        impact: 'medium',
        requester_id: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/tickets', {
            onSuccess: () => {
                setOpen(false);
                setOnBehalf(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="mr-1 size-4" /> New ticket
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Submit a support ticket</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {canCreateForOthers && (
                        <div className="space-y-2">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={onBehalf}
                                    onCheckedChange={(checked) => {
                                        setOnBehalf(checked === true);
                                        if (checked !== true) setData('requester_id', '');
                                    }}
                                />
                                Raise this for someone else
                            </label>
                            {onBehalf && (
                                <div className="grid gap-2">
                                    <Label>Requester</Label>
                                    <Select value={data.requester_id} onValueChange={(value) => setData('requester_id', value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Who is this ticket for?" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {users.map((user) => (
                                                <SelectItem key={user.id} value={user.id.toString()}>
                                                    {user.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.requester_id} />
                                </div>
                            )}
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label>Category</Label>
                        <Select value={data.category_id} onValueChange={(value) => setData('category_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="What is this about?" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem key={category.id} value={category.id.toString()}>
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.category_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ticket-title">Title</Label>
                        <Input id="ticket-title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ticket-description">What happened?</Label>
                        <textarea
                            id="ticket-description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={4}
                            required
                            className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label>How badly does this affect your work?</Label>
                        <Select value={data.impact} onValueChange={(value) => setData('impact', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="low">Low — I can work around it</SelectItem>
                                <SelectItem value="medium">Medium — it slows me down</SelectItem>
                                <SelectItem value="high">High — I am blocked</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.impact} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Submit ticket
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function TicketsIndex({
    tickets,
    categories,
    isManager,
    canCreateForOthers,
    users,
    filters,
}: {
    tickets: { data: TicketRow[]; links: { url: string | null; label: string; active: boolean }[] };
    categories: Category[];
    isManager: boolean;
    canCreateForOthers: boolean;
    users: Person[];
    filters: { status?: string; priority?: string; assigned?: string };
}) {
    const applyFilter = (key: string, value: string) => {
        const next = { ...filters, [key]: value === ALL ? undefined : value };
        router.get('/tickets', next as Record<string, string>, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Service Desk" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">{isManager ? 'Ticket queue' : 'My tickets'}</h1>
                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        {isManager && (
                            <>
                                <Select value={filters.status ?? ALL} onValueChange={(value) => applyFilter('status', value)}>
                                    <SelectTrigger className="w-44">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>All statuses</SelectItem>
                                        {Object.entries(statusLabels).map(([value, label]) => (
                                            <SelectItem key={value} value={value}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={filters.priority ?? ALL} onValueChange={(value) => applyFilter('priority', value)}>
                                    <SelectTrigger className="w-36">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>All priorities</SelectItem>
                                        <SelectItem value="critical">Critical</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="low">Low</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select value={filters.assigned ?? ALL} onValueChange={(value) => applyFilter('assigned', value)}>
                                    <SelectTrigger className="w-36">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>Anyone</SelectItem>
                                        <SelectItem value="unassigned">Unassigned</SelectItem>
                                    </SelectContent>
                                </Select>
                            </>
                        )}
                        <NewTicketDialog categories={categories} canCreateForOthers={canCreateForOthers} users={users} />
                    </div>
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left">
                                <th className="p-3 font-medium">#</th>
                                <th className="p-3 font-medium">Title</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 font-medium">Priority</th>
                                {isManager && <th className="p-3 font-medium">Requester</th>}
                                <th className="p-3 font-medium">Assignee</th>
                                <th className="p-3 font-medium">Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tickets.data.map((ticket) => (
                                <tr key={ticket.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className="p-3 font-mono text-xs">TK-{ticket.ticket_number}</td>
                                    <td className="p-3">
                                        <Link
                                            href={`/tickets/${ticket.id}`}
                                            className="text-brand-600 dark:text-brand-400 font-medium hover:underline"
                                        >
                                            {ticket.title}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={statusVariants[ticket.status]}>{statusLabels[ticket.status]}</Badge>
                                    </td>
                                    <td className={`p-3 font-medium capitalize ${priorityColors[ticket.priority]}`}>{ticket.priority}</td>
                                    {isManager && <td className="p-3">{ticket.requester.name}</td>}
                                    <td className="p-3">{ticket.assignee?.name ?? '—'}</td>
                                    <td className="p-3">{ticket.category.name}</td>
                                </tr>
                            ))}
                            {tickets.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground p-6 text-center text-sm">
                                        No tickets yet. Submit one with “New ticket”.
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
