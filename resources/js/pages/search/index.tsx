import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { statusLabels, statusVariants, type TicketStatus } from '@/pages/tickets/index';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Person = { id: number; name: string };

type SearchResults = {
    tasks: {
        id: number;
        task_number: number;
        title: string;
        board: { id: number; name: string };
        column: { name: string } | null;
        assignee: Person | null;
    }[];
    tickets: { id: number; ticket_number: number; title: string; status: TicketStatus; requester: Person; category: { name: string } }[];
    boards: { id: number; name: string; visibility: string }[];
    users: { id: number; name: string; email: string; job_title: string | null; department: { name: string } | null }[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Search', href: '/search' }];

function Section({ title, count, children }: { title: string; count: number; children: React.ReactNode }) {
    if (count === 0) return null;

    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h2 className="mb-2 text-sm font-semibold">
                {title} <span className="text-muted-foreground font-normal">({count})</span>
            </h2>
            {children}
        </div>
    );
}

export default function SearchPage({ query, results }: { query: string; results: SearchResults }) {
    const [term, setTerm] = useState(query);
    const total = results.tasks.length + results.tickets.length + results.boards.length + results.users.length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Search" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/search', { q: term }, { preserveState: true });
                    }}
                >
                    <Input
                        autoFocus
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Search tasks, tickets, boards, people… (e.g. T-42 or TK-7)"
                        className="h-11 text-base"
                    />
                </form>

                {query.length >= 2 && total === 0 && <p className="text-muted-foreground text-sm">No results for “{query}”.</p>}

                <Section title="Tasks" count={results.tasks.length}>
                    <ul className="divide-sidebar-border/40 dark:divide-sidebar-border/40 divide-y">
                        {results.tasks.map((task) => (
                            <li key={task.id} className="flex flex-wrap items-center gap-2 py-2 text-sm">
                                <span className="text-muted-foreground font-mono text-xs">T-{task.task_number}</span>
                                <Link href={`/boards/${task.board.id}`} className="font-medium hover:underline">
                                    {task.title}
                                </Link>
                                {task.column && <Badge variant="secondary">{task.column.name}</Badge>}
                                <span className="text-muted-foreground ml-auto text-xs">
                                    {task.board.name}
                                    {task.assignee ? ` · ${task.assignee.name}` : ''}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Section>

                <Section title="Tickets" count={results.tickets.length}>
                    <ul className="divide-sidebar-border/40 dark:divide-sidebar-border/40 divide-y">
                        {results.tickets.map((ticket) => (
                            <li key={ticket.id} className="flex flex-wrap items-center gap-2 py-2 text-sm">
                                <span className="text-muted-foreground font-mono text-xs">TK-{ticket.ticket_number}</span>
                                <Link href={`/tickets/${ticket.id}`} className="font-medium hover:underline">
                                    {ticket.title}
                                </Link>
                                <Badge variant={statusVariants[ticket.status]}>{statusLabels[ticket.status]}</Badge>
                                <span className="text-muted-foreground ml-auto text-xs">
                                    {ticket.category.name} · {ticket.requester.name}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Section>

                <Section title="Boards" count={results.boards.length}>
                    <ul className="space-y-1.5">
                        {results.boards.map((board) => (
                            <li key={board.id} className="flex items-center gap-2 text-sm">
                                <Link href={`/boards/${board.id}`} className="font-medium hover:underline">
                                    {board.name}
                                </Link>
                                <Badge variant="secondary" className="ml-auto">
                                    {board.visibility}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                </Section>

                <Section title="People" count={results.users.length}>
                    <ul className="space-y-1.5">
                        {results.users.map((person) => (
                            <li key={person.id} className="text-sm">
                                <span className="font-medium">{person.name}</span>
                                <span className="text-muted-foreground ml-2 text-xs">
                                    {person.job_title ?? '—'} · {person.department?.name ?? 'No department'} · {person.email}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Section>
            </div>
        </AppLayout>
    );
}
