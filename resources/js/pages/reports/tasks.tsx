import InputError from '@/components/input-error';
import { Pagination, type Paginated } from '@/components/pagination';
import { SortableHeader, type SortState } from '@/components/sortable-header';
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
import { Bookmark, Plus, X } from 'lucide-react';
import { useState } from 'react';

type Person = { id: number; name: string };

type SavedFilter = { id: number; name: string; filters: Record<string, string> };

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

function SaveFilterDialog({ currentFilters }: { currentFilters: Record<string, string> }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({ name: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({ ...form, scope: 'reports.tasks', filters: currentFilters }));
        post('/saved-filters', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Plus className="mr-1 size-4" /> Save filter
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Save current filter</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="filter-name">Name</Label>
                        <Input id="filter-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                        <InputError message={errors.name} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function BulkReassignBar({ selectedIds, people, onDone }: { selectedIds: number[]; people: Person[]; onDone: () => void }) {
    const [assigneeId, setAssigneeId] = useState(ALL);
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.post(
            '/tasks/bulk-reassign',
            { task_ids: selectedIds, assignee_id: assigneeId === ALL ? null : Number(assigneeId) },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: onDone,
            },
        );
    };

    return (
        <div className="bg-muted/50 border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center gap-2 rounded-xl border p-3">
            <span className="text-sm font-medium">{selectedIds.length} selected</span>
            <Select value={assigneeId} onValueChange={setAssigneeId}>
                <SelectTrigger className="w-48" aria-label="Reassign to">
                    <SelectValue placeholder="Reassign to…" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Unassign</SelectItem>
                    {people.map((person) => (
                        <SelectItem key={person.id} value={person.id.toString()}>
                            {person.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button size="sm" onClick={submit} disabled={processing}>
                Apply
            </Button>
            <Button size="sm" variant="ghost" onClick={onDone}>
                Cancel
            </Button>
        </div>
    );
}

export default function TasksReport({
    tasks,
    filter,
    filters,
    departments,
    people,
    selected,
    savedFilters,
    sort: sortColumn,
    direction,
}: {
    tasks: Paginated<ReportTask>;
    filter: string;
    filters: string[];
    departments: Person[];
    people: Person[];
    selected: { department_id?: string; assignee_id?: string };
    savedFilters: SavedFilter[];
    sort: string | null;
    direction: 'asc' | 'desc';
}) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const sort: SortState = { column: sortColumn, direction };

    const currentFilters = Object.fromEntries(
        Object.entries({ filter, ...selected, sort: sortColumn, direction }).filter(([, value]) => value && value !== ALL),
    ) as Record<string, string>;

    const apply = (params: Record<string, string | undefined>) => {
        setSelectedIds([]);
        router.get(
            '/reports/tasks',
            Object.fromEntries(Object.entries({ ...currentFilters, ...params }).filter(([, value]) => value && value !== ALL)) as Record<
                string,
                string
            >,
            { preserveState: true, preserveScroll: true },
        );
    };

    const onSort = (column: string) => {
        apply({ sort: column, direction: sort.column === column && sort.direction === 'asc' ? 'desc' : 'asc' });
    };

    const applySavedFilter = (savedFilter: SavedFilter) => {
        setSelectedIds([]);
        router.get('/reports/tasks', savedFilter.filters, { preserveState: true, preserveScroll: true });
    };

    const deleteSavedFilter = (id: number) => {
        router.delete(`/saved-filters/${id}`, { preserveScroll: true });
    };

    const allSelected = tasks.data.length > 0 && selectedIds.length === tasks.data.length;

    const toggleAll = () => {
        setSelectedIds(allSelected ? [] : tasks.data.map((task) => task.id));
    };

    const toggleOne = (id: number) => {
        setSelectedIds((current) => (current.includes(id) ? current.filter((existing) => existing !== id) : [...current, id]));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Task report" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">Task report</h1>
                    <span className="text-muted-foreground text-sm">{tasks.total} tasks</span>
                    <Link href="/reports/workload" className="text-brand-600 dark:text-brand-400 text-sm hover:underline">
                        Workload →
                    </Link>
                    <Link href="/reports/remote-support" className="text-brand-600 dark:text-brand-400 text-sm hover:underline">
                        Remote support →
                    </Link>
                    <div className="ml-auto flex flex-wrap gap-2">
                        <Select value={filter} onValueChange={(value) => apply({ filter: value })}>
                            <SelectTrigger className="w-48" aria-label="Filter by status">
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
                            <SelectTrigger className="w-48" aria-label="Filter by department">
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
                            <SelectTrigger className="w-44" aria-label="Filter by assignee">
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
                        <SaveFilterDialog currentFilters={currentFilters} />
                    </div>
                </div>

                {savedFilters.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Bookmark className="text-muted-foreground size-4" />
                        {savedFilters.map((savedFilter) => (
                            <span
                                key={savedFilter.id}
                                className="border-sidebar-border/70 dark:border-sidebar-border flex items-center gap-1 rounded-full border py-1 pr-1 pl-3 text-sm"
                            >
                                <button type="button" onClick={() => applySavedFilter(savedFilter)} className="hover:underline">
                                    {savedFilter.name}
                                </button>
                                <button
                                    type="button"
                                    aria-label={`Delete ${savedFilter.name}`}
                                    onClick={() => deleteSavedFilter(savedFilter.id)}
                                    className="text-muted-foreground hover:text-destructive rounded-full p-0.5"
                                >
                                    <X className="size-3" />
                                </button>
                            </span>
                        ))}
                    </div>
                )}

                {selectedIds.length > 0 && <BulkReassignBar selectedIds={selectedIds} people={people} onDone={() => setSelectedIds([])} />}

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-sidebar-border/70 dark:border-sidebar-border border-b text-left">
                                <th className="w-10 p-3">
                                    <Checkbox checked={allSelected} onCheckedChange={toggleAll} aria-label="Select all on this page" />
                                </th>
                                <SortableHeader column="task_number" sort={sort} onSort={onSort} className="p-3">
                                    #
                                </SortableHeader>
                                <SortableHeader column="title" sort={sort} onSort={onSort} className="p-3">
                                    Task
                                </SortableHeader>
                                <th className="p-3 font-medium">Board</th>
                                <th className="p-3 font-medium">Column</th>
                                <th className="p-3 font-medium">Assignee</th>
                                <th className="p-3 font-medium">Department</th>
                                <SortableHeader column="priority" sort={sort} onSort={onSort} className="p-3">
                                    Priority
                                </SortableHeader>
                                <SortableHeader column="due_at" sort={sort} onSort={onSort} className="p-3">
                                    Due
                                </SortableHeader>
                            </tr>
                        </thead>
                        <tbody>
                            {tasks.data.map((task) => {
                                const isOverdue = task.due_at !== null && !task.completed_at && new Date(task.due_at) < new Date();
                                return (
                                    <tr key={task.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                        <td className="p-3">
                                            <Checkbox
                                                checked={selectedIds.includes(task.id)}
                                                onCheckedChange={() => toggleOne(task.id)}
                                                aria-label={`Select ${task.title}`}
                                            />
                                        </td>
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
                                    <td colSpan={9} className="text-muted-foreground p-6 text-center">
                                        No tasks match this filter.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination meta={tasks} />
            </div>
        </AppLayout>
    );
}
