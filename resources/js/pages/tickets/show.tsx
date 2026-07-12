import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { statusLabels, statusVariants, type TicketStatus } from '@/pages/tickets/index';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Lock, Paperclip } from 'lucide-react';
import { useRef, useState } from 'react';

type Person = { id: number; name: string };

type TicketDetail = {
    id: number;
    ticket_number: number;
    title: string;
    description: string;
    status: TicketStatus;
    priority: 'critical' | 'high' | 'medium' | 'low';
    impact: string;
    requester: Person & { email: string };
    assignee: Person | null;
    category: { id: number; name: string };
    department: { id: number; name: string } | null;
    converted_task: { id: number; task_number: number; title: string; board_id: number } | null;
    due_at: string | null;
    first_responded_at: string | null;
    resolved_at: string | null;
    resolution_method: string | null;
    resolution_summary: string | null;
    time_spent_minutes: number;
    created_at: string;
    status_history: {
        id: number;
        from_status: string | null;
        to_status: TicketStatus;
        reason: string | null;
        changed_by: Person;
        created_at: string;
    }[];
};

type TicketComment = {
    id: number;
    body: string;
    is_internal: boolean;
    user: Person;
    created_at: string;
    replies: { id: number; body: string; is_internal: boolean; user: Person; created_at: string }[];
};

type TicketAttachment = {
    id: number;
    original_name: string;
    size_bytes: number;
    uploader: Person;
};

type BoardOption = { id: number; name: string; columns: { id: number; board_id: number; name: string }[] };

function ResolveDialog({ ticketId }: { ticketId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        resolution_method: 'remote',
        resolution_summary: '',
        time_spent_minutes: 30,
    });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">Resolve</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Resolve ticket</DialogTitle>
                </DialogHeader>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(`/tickets/${ticketId}/resolve`, { preserveScroll: true, onSuccess: () => setOpen(false) });
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-2">
                        <Label>How was it handled?</Label>
                        <Select value={data.resolution_method} onValueChange={(value) => setData('resolution_method', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="remote">Remote</SelectItem>
                                <SelectItem value="office">In office</SelectItem>
                                <SelectItem value="onsite">Onsite intervention</SelectItem>
                                <SelectItem value="third_party">Third party</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.resolution_method} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="resolution-summary">Resolution notes</Label>
                        <textarea
                            id="resolution-summary"
                            value={data.resolution_summary}
                            onChange={(e) => setData('resolution_summary', e.target.value)}
                            rows={3}
                            required
                            className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <InputError message={errors.resolution_summary} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="time-spent">Time spent (minutes)</Label>
                        <Input
                            id="time-spent"
                            type="number"
                            min={0}
                            value={data.time_spent_minutes}
                            onChange={(e) => setData('time_spent_minutes', Number(e.target.value))}
                        />
                        <InputError message={errors.time_spent_minutes} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Mark resolved
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ConvertDialog({ ticketId, boards }: { ticketId: number; boards: BoardOption[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ board_id: '', board_column_id: '' });
    const selectedBoard = boards.find((board) => board.id.toString() === data.board_id);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="secondary">
                    Convert to task
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Convert to task</DialogTitle>
                </DialogHeader>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(`/tickets/${ticketId}/convert-to-task`, { onSuccess: () => setOpen(false) });
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-2">
                        <Label>Board</Label>
                        <Select
                            value={data.board_id}
                            onValueChange={(value) => setData((current) => ({ ...current, board_id: value, board_column_id: '' }))}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a board" />
                            </SelectTrigger>
                            <SelectContent>
                                {boards.map((board) => (
                                    <SelectItem key={board.id} value={board.id.toString()}>
                                        {board.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.board_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Column</Label>
                        <Select value={data.board_column_id} onValueChange={(value) => setData('board_column_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a column" />
                            </SelectTrigger>
                            <SelectContent>
                                {(selectedBoard?.columns ?? []).map((column) => (
                                    <SelectItem key={column.id} value={column.id.toString()}>
                                        {column.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.board_column_id} />
                    </div>
                    <Button type="submit" disabled={processing || data.board_column_id === ''}>
                        Create linked task
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function TicketShow({
    ticket,
    comments,
    attachments,
    technicians,
    boards,
    isManager,
    allowedTransitions,
}: {
    ticket: TicketDetail;
    comments: TicketComment[];
    attachments: TicketAttachment[];
    technicians: Person[];
    boards: BoardOption[];
    isManager: boolean;
    allowedTransitions: TicketStatus[];
}) {
    const { auth } = usePage<SharedData>().props;
    const fileInput = useRef<HTMLInputElement>(null);
    const commentForm = useForm<{ body: string; is_internal: boolean }>({ body: '', is_internal: false });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Service Desk', href: '/tickets' },
        { title: `TK-${ticket.ticket_number}`, href: `/tickets/${ticket.id}` },
    ];

    const submitComment = (e: React.FormEvent) => {
        e.preventDefault();
        commentForm.post(`/tickets/${ticket.id}/comments`, {
            preserveScroll: true,
            onSuccess: () => commentForm.reset(),
        });
    };

    const canReopen = (ticket.status === 'resolved' || ticket.status === 'closed') && (isManager || auth.user.id === ticket.requester.id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`TK-${ticket.ticket_number}`} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">
                        <span className="text-muted-foreground mr-2 font-mono text-base">TK-{ticket.ticket_number}</span>
                        {ticket.title}
                    </h1>
                    <Badge variant={statusVariants[ticket.status]}>{statusLabels[ticket.status]}</Badge>
                    <Badge variant="secondary" className="capitalize">
                        {ticket.priority}
                    </Badge>
                </div>

                {isManager && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Select
                            value={ticket.assignee?.id.toString() ?? ''}
                            onValueChange={(value) =>
                                router.post(`/tickets/${ticket.id}/assign`, { assigned_to: Number(value) }, { preserveScroll: true })
                            }
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="Assign technician…" />
                            </SelectTrigger>
                            <SelectContent>
                                {technicians.map((technician) => (
                                    <SelectItem key={technician.id} value={technician.id.toString()}>
                                        {technician.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {allowedTransitions
                            .filter((status) => status !== 'resolved')
                            .map((status) => (
                                <Button
                                    key={status}
                                    size="sm"
                                    variant="outline"
                                    onClick={() => router.post(`/tickets/${ticket.id}/transition`, { status }, { preserveScroll: true })}
                                >
                                    {statusLabels[status]}
                                </Button>
                            ))}
                        {ticket.status !== 'resolved' && ticket.status !== 'closed' && <ResolveDialog ticketId={ticket.id} />}
                        {!ticket.converted_task && <ConvertDialog ticketId={ticket.id} boards={boards} />}
                    </div>
                )}

                {canReopen && (
                    <div>
                        <Button size="sm" variant="outline" onClick={() => router.post(`/tickets/${ticket.id}/reopen`, {}, { preserveScroll: true })}>
                            Reopen ticket
                        </Button>
                    </div>
                )}

                <div className="border-sidebar-border/70 dark:border-sidebar-border grid gap-3 rounded-xl border p-4 text-sm sm:grid-cols-2">
                    <div>
                        <span className="text-muted-foreground">Requester:</span> {ticket.requester.name}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Department:</span> {ticket.department?.name ?? '—'}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Category:</span> {ticket.category.name}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Assignee:</span> {ticket.assignee?.name ?? 'Unassigned'}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Due:</span> {ticket.due_at ? new Date(ticket.due_at).toLocaleString() : '—'}
                    </div>
                    <div>
                        <span className="text-muted-foreground">First response:</span>{' '}
                        {ticket.first_responded_at ? new Date(ticket.first_responded_at).toLocaleString() : 'Pending'}
                    </div>
                    {ticket.converted_task && (
                        <div className="sm:col-span-2">
                            <span className="text-muted-foreground">Linked task:</span>{' '}
                            <Link href={`/boards/${ticket.converted_task.board_id}`} className="text-brand-600 dark:text-brand-400 hover:underline">
                                T-{ticket.converted_task.task_number} {ticket.converted_task.title}
                            </Link>
                        </div>
                    )}
                    {ticket.resolution_method && (
                        <div className="sm:col-span-2">
                            <span className="text-muted-foreground">Resolution:</span> <span className="capitalize">{ticket.resolution_method}</span>
                            {' — '}
                            {ticket.resolution_summary} ({ticket.time_spent_minutes} min)
                        </div>
                    )}
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Description</h2>
                    <p className="text-sm whitespace-pre-wrap">{ticket.description}</p>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Attachments</h2>
                    <ul className="space-y-1.5">
                        {attachments.map((attachment) => (
                            <li key={attachment.id} className="flex items-center gap-2 text-sm">
                                <Paperclip className="text-muted-foreground size-3.5" />
                                <a href={`/attachments/${attachment.id}`} className="text-brand-600 dark:text-brand-400 hover:underline">
                                    {attachment.original_name}
                                </a>
                            </li>
                        ))}
                        {attachments.length === 0 && <li className="text-muted-foreground text-sm">No attachments.</li>}
                    </ul>
                    <input
                        ref={fileInput}
                        type="file"
                        className="hidden"
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (!file) return;
                            router.post(`/tickets/${ticket.id}/attachments`, { file }, { forceFormData: true, preserveScroll: true });
                        }}
                    />
                    <Button type="button" size="sm" variant="secondary" className="mt-2" onClick={() => fileInput.current?.click()}>
                        Upload file
                    </Button>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Comments</h2>
                    <ul className="space-y-3">
                        {comments.map((comment) => (
                            <li
                                key={comment.id}
                                className={`rounded-lg border p-3 ${
                                    comment.is_internal
                                        ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/30'
                                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                                }`}
                            >
                                <div className="mb-1 flex items-center gap-2 text-xs">
                                    <span className="font-semibold">{comment.user.name}</span>
                                    {comment.is_internal && (
                                        <span className="flex items-center gap-1 text-amber-700 dark:text-amber-400">
                                            <Lock className="size-3" /> Internal note
                                        </span>
                                    )}
                                    <span className="text-muted-foreground">{new Date(comment.created_at).toLocaleString()}</span>
                                </div>
                                <p className="text-sm whitespace-pre-wrap">{comment.body}</p>
                            </li>
                        ))}
                        {comments.length === 0 && <li className="text-muted-foreground text-sm">No comments yet.</li>}
                    </ul>
                    <form onSubmit={submitComment} className="mt-3 space-y-2">
                        <textarea
                            value={commentForm.data.body}
                            onChange={(e) => commentForm.setData('body', e.target.value)}
                            rows={2}
                            placeholder="Write a comment…"
                            className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <div className="flex items-center gap-3">
                            <Button type="submit" size="sm" disabled={commentForm.processing}>
                                Comment
                            </Button>
                            {isManager && (
                                <label className="flex items-center gap-1.5 text-xs">
                                    <Checkbox
                                        checked={commentForm.data.is_internal}
                                        onCheckedChange={(checked) => commentForm.setData('is_internal', checked === true)}
                                    />
                                    <Lock className="size-3" /> Internal note (hidden from requester)
                                </label>
                            )}
                        </div>
                    </form>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Status history</h2>
                    <ul className="space-y-1.5">
                        {ticket.status_history.map((entry) => (
                            <li key={entry.id} className="text-muted-foreground text-xs">
                                <span className="text-foreground font-medium">{entry.changed_by.name}</span>{' '}
                                {entry.from_status ? `${statusLabels[entry.from_status as TicketStatus]} → ` : ''}
                                {statusLabels[entry.to_status]}
                                {entry.reason && ` — ${entry.reason}`}
                                {' · '}
                                {new Date(entry.created_at).toLocaleString()}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AppLayout>
    );
}
