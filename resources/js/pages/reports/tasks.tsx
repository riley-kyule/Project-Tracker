import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type Person = { id: number; name: string };

type ReportTask = {
    id: number;
    task_number: number;
    title: string;
    priority: string;
    due_at: string | null;
    completed_at: string | null;
    ceo_priority: boolean;
    board: { id: number; name: string };
    column: { id: number; name: string; semantic_status: string } | null;
    assignee: Person | null;
    department: { id: number; name: string } | null;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports/tasks' }];

const filterLabels: Record<string, string> = {
    all: 'All open',
    due_today: 'Due today',
    overdue: 'Overdue',
    blocked: 'Blocked',
    awaiting_review: 'Awaiting review',
    ceo_priority: 'CEO priority',
    completed_week: 'Completed this week',
    unassigned: 'Unassigned',
};

const ALL = 'all';

export default function TasksReport({
    tasks,
    filter,
    filters,
    departments,
    people,
    selected,
}: {
    tasks: { data: ReportTask[]; total: number };
    filter: string;
    filters: string[];
    departments: Person[];
    people: Person[];
    selected: { department_id?: string; assignee_id?: string };
}) {
    const apply = (params: Record<string, string | undefined>) => {
        router.get(
            '/reports/tasks',
            Object.fromEntries(Object.entries({ filter, ...selected, ...params }).filter(([, value]) => value && value !== ALL)) as Record<
                string,
                string
            >,
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Task report" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">Task report</h1>
                    <span className="text-muted-foreground text-sm">{tasks.total} tasks</span>
                    <Link href="/reports/remote-support" className="text-brand-600 dark:text-brand-400 text-sm hover:underline">
                        Remote support →
                    </Link>
                    <div className="ml-auto flex flex-wrap gap-2">
                        <Select value={filter} onValueChange={(value) => apply({ filter: value })}>
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {filters.map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {filterLabels[value] ?? value}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={selected.department_id ?? ALL} onValueChange={(value) => apply({ department_id: value })}>
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All departments</SelectItem>
                                {departments.map((department) => (
                                    <SelectItem key={department.id} value={department.id.toString()}>
                                        {department.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={selected.assignee_id ?? ALL} onValueChange={(value) => apply({ assignee_id: value })}>
                            <SelectTrigger className="w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All assignees</SelectItem>
                                {people.map((person) => (
                                    <SelectItem key={person.id} value={person.id.toString()}>
                                        {person.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-sidebar-border/70 dark:border-sidebar-border border-b text-left">
                                <th className="p-3 font-medium">#</th>
                                <th className="p-3 font-medium">Task</th>
                                <th className="p-3 font-medium">Board</th>
                                <th className="p-3 font-medium">Column</th>
                                <th className="p-3 font-medium">Assignee</th>
                                <th className="p-3 font-medium">Department</th>
                                <th className="p-3 font-medium">Priority</th>
                                <th className="p-3 font-medium">Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tasks.data.map((task) => {
                                const isOverdue = task.due_at !== null && !task.completed_at && new Date(task.due_at) < new Date();
                                return (
                                    <tr key={task.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                        <td className="p-3 font-mono text-xs">T-{task.task_number}</td>
                                        <td className="p-3">
                                            <Link href={`/boards/${task.board.id}`} className="text-brand-600 dark:text-brand-400 hover:underline">
                                                {task.title}
                                            </Link>
                                            {task.ceo_priority && <span className="ml-1 text-amber-500">★</span>}
                                        </td>
                                        <td className="p-3">{task.board.name}</td>
                                        <td className="p-3">
                                            {task.column && (
                                                <Badge variant={task.column.semantic_status === 'blocked' ? 'destructive' : 'secondary'}>
                                                    {task.column.name}
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="p-3">{task.assignee?.name ?? '—'}</td>
                                        <td className="p-3">{task.department?.name ?? '—'}</td>
                                        <td className="p-3 capitalize">{task.priority}</td>
                                        <td className={`p-3 ${isOverdue ? 'text-destructive font-semibold' : ''}`}>
                                            {task.due_at ? new Date(task.due_at).toLocaleDateString() : '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                            {tasks.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="text-muted-foreground p-6 text-center">
                                        No tasks match this filter.
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
