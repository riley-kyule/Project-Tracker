import { TaskCard, TaskDialog, type BoardTask, type Can, type ColumnOption, type LabelOption, type Member } from '@/components/board/task-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCorners,
    useDroppable,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragOverEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, MoreVertical, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

type BoardTaskOption = { id: number; title: string; task_number: number };

type BlockedMove = { taskId: number; columnId: number; position: number; message: string };

function DependencyOverrideDialog({ move, onClose }: { move: BlockedMove; onClose: () => void }) {
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            `/tasks/${move.taskId}/move`,
            { board_column_id: move.columnId, position: move.position, override_reason: reason },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onError: (errors) => setError(errors.dependencies ?? 'Could not override this dependency.'),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Task is blocked</DialogTitle>
                </DialogHeader>
                <p className="text-muted-foreground text-sm">{move.message}</p>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="override-reason">Override reason (required)</Label>
                        <Input id="override-reason" value={reason} onChange={(e) => setReason(e.target.value)} required />
                        {error && <p className="text-destructive text-sm">{error}</p>}
                    </div>
                    <Button type="submit" disabled={processing || reason.trim() === ''}>
                        Override and move
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type Column = {
    id: number;
    name: string;
    semantic_status: string;
    is_completion_column: boolean;
    wip_limit: number | null;
    tasks: BoardTask[];
};

const SEMANTIC_STATUS_OPTIONS: [string, string][] = [
    ['idea', 'Idea'],
    ['backlog', 'Backlog'],
    ['ready', 'Ready'],
    ['active', 'In progress'],
    ['blocked', 'Blocked'],
    ['review', 'Awaiting review'],
    ['completed', 'Completed (marks tasks done)'],
    ['archived', 'Archived (hides tasks)'],
    ['custom', 'Custom'],
];

const SEMANTIC_STATUS_DESCRIPTIONS: Record<string, string> = {
    idea: "Early-stage ideas that aren't ready to be worked on yet.",
    backlog: 'Not started — waiting to be picked up.',
    ready: 'Ready to be worked on next.',
    active: 'Currently being worked on.',
    blocked: "Blocked on something and can't proceed right now.",
    review: 'Work is done and awaiting review or approval.',
    completed: 'Marks a task as done — moving a card here completes it.',
    archived: 'Hides a task from active views without deleting it.',
    custom: 'A custom stage with no special automatic behavior.',
};

function ColumnDialog({ boardId, column, onClose }: { boardId: number; column?: Column; onClose: () => void }) {
    const { data, setData, post, patch, processing, errors, transform } = useForm({
        name: column?.name ?? '',
        semantic_status: column?.semantic_status ?? 'custom',
        wip_limit: column?.wip_limit?.toString() ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({ ...form, wip_limit: form.wip_limit === '' ? null : Number(form.wip_limit) }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (column) {
            patch(`/board-columns/${column.id}`, options);
        } else {
            post(`/boards/${boardId}/columns`, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{column ? `Edit ${column.name}` : 'New column'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="column-name">Name</Label>
                        <Input id="column-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Column type</Label>
                        <Select value={data.semantic_status} onValueChange={(value) => setData('semantic_status', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {SEMANTIC_STATUS_OPTIONS.map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.semantic_status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="column-wip">WIP limit (optional)</Label>
                        <Input id="column-wip" type="number" min={1} value={data.wip_limit} onChange={(e) => setData('wip_limit', e.target.value)} />
                        <InputError message={errors.wip_limit} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        {column ? 'Save changes' : 'Create column'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type Board = {
    id: number;
    name: string;
    department: { id: number; name: string } | null;
    columns: Column[];
};

const ALL = 'all';

function QuickAdd({ boardId, columnId }: { boardId: number; columnId: number }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        board_column_id: columnId,
        priority: 'medium',
    });

    if (!adding) {
        return (
            <button
                type="button"
                onClick={() => setAdding(true)}
                className="text-muted-foreground hover:text-foreground flex w-full items-center gap-1 rounded-md p-2 text-sm"
            >
                <Plus className="size-4" /> Add task
            </button>
        );
    }

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(`/boards/${boardId}/tasks`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        reset('title');
                        setAdding(false);
                    },
                });
            }}
            className="p-1"
        >
            <Input
                autoFocus
                placeholder="Task title"
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                onBlur={() => data.title === '' && setAdding(false)}
                disabled={processing}
            />
        </form>
    );
}

function BoardColumn({
    column,
    columnOptions,
    boardId,
    canCreate,
    canManage,
    isFirst,
    isLast,
    onOpenTask,
    onEdit,
    onMove,
    onMoveTask,
}: {
    column: Column;
    columnOptions: ColumnOption[];
    boardId: number;
    canCreate: boolean;
    canManage: boolean;
    isFirst: boolean;
    isLast: boolean;
    onOpenTask: (task: BoardTask) => void;
    onEdit: (column: Column) => void;
    onMove: (columnId: number, direction: -1 | 1) => void;
    onMoveTask: (taskId: number, columnId: number) => void;
}) {
    const { setNodeRef } = useDroppable({ id: `column-${column.id}` });
    const overLimit = column.wip_limit !== null && column.tasks.length > column.wip_limit;

    const destroy = () => {
        if (column.tasks.length > 0 && !confirm(`Delete "${column.name}"? It has no tasks left to lose, so this can't be undone.`)) {
            return;
        }
        router.delete(`/board-columns/${column.id}`, {
            preserveScroll: true,
            onError: (errors) => errors.column && toast.error(errors.column),
        });
    };

    return (
        <div className="bg-sidebar dark:bg-sidebar border-sidebar-border/70 dark:border-sidebar-border flex w-[85vw] max-w-72 shrink-0 flex-col rounded-xl border">
            <div className="flex items-center justify-between gap-1 p-3 pb-1">
                <TooltipProvider>
                    <Tooltip delayDuration={300}>
                        <TooltipTrigger asChild>
                            <span className="text-sm font-semibold">{column.name}</span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {SEMANTIC_STATUS_DESCRIPTIONS[column.semantic_status] ?? SEMANTIC_STATUS_DESCRIPTIONS.custom}
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
                <span className={`text-xs ${overLimit ? 'text-destructive font-semibold' : 'text-muted-foreground'}`}>
                    {column.tasks.length}
                    {column.wip_limit !== null && ` / ${column.wip_limit}`}
                </span>
                {canManage && (
                    <div className="ml-auto flex items-center">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="size-6 p-0"
                            aria-label={`Move ${column.name} left`}
                            disabled={isFirst}
                            onClick={() => onMove(column.id, -1)}
                        >
                            <ChevronLeft className="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="size-6 p-0"
                            aria-label={`Move ${column.name} right`}
                            disabled={isLast}
                            onClick={() => onMove(column.id, 1)}
                        >
                            <ChevronRight className="size-3.5" />
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm" className="size-6 p-0" aria-label={`${column.name} options`}>
                                    <MoreVertical className="size-3.5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => onEdit(column)}>
                                    <Pencil className="mr-2 size-3.5" /> Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem className="text-destructive focus:text-destructive" onClick={destroy}>
                                    <Trash2 className="mr-2 size-3.5" /> Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                )}
            </div>
            <SortableContext items={column.tasks.map((task) => task.id)} strategy={verticalListSortingStrategy}>
                <div ref={setNodeRef} className="flex min-h-16 flex-1 flex-col gap-2 p-2">
                    {column.tasks.map((task) => (
                        <TaskCard key={task.id} task={task} onOpen={onOpenTask} columns={columnOptions} onMove={onMoveTask} />
                    ))}
                </div>
            </SortableContext>
            {canCreate && <QuickAdd boardId={boardId} columnId={column.id} />}
        </div>
    );
}

export default function BoardShow({
    board,
    boardTaskOptions,
    members,
    allMembers,
    labels,
    can,
}: {
    board: Board;
    boardTaskOptions: BoardTaskOption[];
    members: Member[];
    allMembers: Member[];
    labels: LabelOption[];
    can: Can;
}) {
    const [columns, setColumns] = useState<Column[]>(board.columns);
    const [activeTask, setActiveTask] = useState<BoardTask | null>(null);
    const [openTask, setOpenTask] = useState<BoardTask | null>(null);
    const [blockedMove, setBlockedMove] = useState<BlockedMove | null>(null);
    const [approvalBlockedMessage, setApprovalBlockedMessage] = useState<string | null>(null);
    const [columnDialog, setColumnDialog] = useState<'new' | Column | null>(null);
    const [search, setSearch] = useState('');
    const [assigneeFilter, setAssigneeFilter] = useState(ALL);
    const [priorityFilter, setPriorityFilter] = useState(ALL);

    useEffect(() => setColumns(board.columns), [board.columns]);

    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

    const filtering = search !== '' || assigneeFilter !== ALL || priorityFilter !== ALL;

    const visibleColumns = useMemo(
        () =>
            columns.map((column) => ({
                ...column,
                tasks: column.tasks.filter(
                    (task) =>
                        (search === '' || task.title.toLowerCase().includes(search.toLowerCase())) &&
                        (assigneeFilter === ALL || task.assignee?.id.toString() === assigneeFilter) &&
                        (priorityFilter === ALL || task.priority === priorityFilter),
                ),
            })),
        [columns, search, assigneeFilter, priorityFilter],
    );

    const columnOptions: ColumnOption[] = useMemo(() => columns.map((column) => ({ id: column.id, name: column.name })), [columns]);

    const findColumn = (taskId: number) => columns.find((column) => column.tasks.some((task) => task.id === taskId));

    const moveColumn = (columnId: number, direction: -1 | 1) => {
        const ids = columns.map((c) => c.id);
        const index = ids.indexOf(columnId);
        const swapWith = index + direction;
        if (swapWith < 0 || swapWith >= ids.length) return;
        [ids[index], ids[swapWith]] = [ids[swapWith], ids[index]];
        router.post(`/boards/${board.id}/reorder-columns`, { column_ids: ids }, { preserveScroll: true });
    };

    const handleDragStart = ({ active }: DragStartEvent) => {
        const column = findColumn(Number(active.id));
        setActiveTask(column?.tasks.find((task) => task.id === Number(active.id)) ?? null);
    };

    const handleDragOver = ({ active, over }: DragOverEvent) => {
        if (!over) return;
        const activeId = Number(active.id);
        const source = findColumn(activeId);
        const target = String(over.id).startsWith('column-')
            ? columns.find((column) => `column-${column.id}` === String(over.id))
            : findColumn(Number(over.id));

        if (!source || !target || source.id === target.id) return;

        setColumns((current) => {
            const task = source.tasks.find((t) => t.id === activeId);
            if (!task) return current;
            return current.map((column) => {
                if (column.id === source.id) {
                    return { ...column, tasks: column.tasks.filter((t) => t.id !== activeId) };
                }
                if (column.id === target.id) {
                    const overIndex = column.tasks.findIndex((t) => t.id === Number(over.id));
                    const insertAt = overIndex >= 0 ? overIndex : column.tasks.length;
                    const tasks = [...column.tasks];
                    tasks.splice(insertAt, 0, task);
                    return { ...column, tasks };
                }
                return column;
            });
        });
    };

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        setActiveTask(null);
        if (!over) return;

        const activeId = Number(active.id);
        const column = findColumn(activeId);
        if (!column) return;

        let index = column.tasks.findIndex((task) => task.id === activeId);
        const overId = Number(over.id);

        if (!Number.isNaN(overId) && overId !== activeId) {
            const overIndex = column.tasks.findIndex((task) => task.id === overId);
            if (overIndex >= 0) {
                setColumns((current) =>
                    current.map((c) => {
                        if (c.id !== column.id) return c;
                        const tasks = [...c.tasks];
                        const [moved] = tasks.splice(index, 1);
                        tasks.splice(overIndex, 0, moved);
                        return { ...c, tasks };
                    }),
                );
                index = overIndex;
            }
        }

        const position = index + 1;

        router.post(
            `/tasks/${activeId}/move`,
            { board_column_id: column.id, position },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    if (errors.dependencies) {
                        setBlockedMove({ taskId: activeId, columnId: column.id, position, message: errors.dependencies });
                    } else if (errors.approval) {
                        setApprovalBlockedMessage(errors.approval);
                    }
                    // Local optimistic state may now disagree with the server; resync.
                    router.reload({ only: ['board'] });
                },
            },
        );
    };

    // Picked from the "Move to" dropdown rather than dragged — there's no local
    // reorder to compute, so just append to the end of the target column (the
    // backend clamps an oversized position down to the real max anyway).
    const moveTaskToColumn = (taskId: number, columnId: number) => {
        const position = 9999;

        router.post(
            `/tasks/${taskId}/move`,
            { board_column_id: columnId, position },
            {
                preserveScroll: true,
                onError: (errors) => {
                    if (errors.dependencies) {
                        setBlockedMove({ taskId, columnId, position, message: errors.dependencies });
                    } else if (errors.approval) {
                        setApprovalBlockedMessage(errors.approval);
                    }
                },
            },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Boards', href: '/boards' },
        { title: board.name, href: `/boards/${board.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={board.name} />
            <div className="flex h-full flex-col gap-3 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">{board.name}</h1>
                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        <Input
                            placeholder="Search tasks…"
                            aria-label="Search tasks on this board"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full sm:w-48"
                        />
                        <Select value={assigneeFilter} onValueChange={setAssigneeFilter}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All assignees</SelectItem>
                                {members.map((member) => (
                                    <SelectItem key={member.id} value={member.id.toString()}>
                                        {member.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={priorityFilter} onValueChange={setPriorityFilter}>
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
                        {filtering && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    setAssigneeFilter(ALL);
                                    setPriorityFilter(ALL);
                                }}
                            >
                                Clear
                            </Button>
                        )}
                    </div>
                </div>
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCorners}
                    onDragStart={handleDragStart}
                    onDragOver={filtering ? undefined : handleDragOver}
                    onDragEnd={handleDragEnd}
                >
                    <div className="flex flex-1 gap-3 overflow-x-auto pb-2">
                        {visibleColumns.map((column, index) => (
                            <BoardColumn
                                key={column.id}
                                column={column}
                                columnOptions={columnOptions}
                                boardId={board.id}
                                canCreate={can.createTask && !filtering}
                                canManage={can.manage}
                                isFirst={index === 0}
                                isLast={index === visibleColumns.length - 1}
                                onOpenTask={setOpenTask}
                                onEdit={setColumnDialog}
                                onMove={moveColumn}
                                onMoveTask={moveTaskToColumn}
                            />
                        ))}
                        {can.manage && (
                            <button
                                type="button"
                                onClick={() => setColumnDialog('new')}
                                className="text-muted-foreground hover:text-foreground hover:border-foreground/30 flex w-24 shrink-0 flex-col items-center justify-center gap-1 self-start rounded-xl border border-dashed p-3 text-xs"
                            >
                                <Plus className="size-4" /> Add column
                            </button>
                        )}
                    </div>
                    <DragOverlay>{activeTask && <TaskCard task={activeTask} overlay />}</DragOverlay>
                </DndContext>
            </div>
            {openTask && (
                <TaskDialog
                    task={openTask}
                    members={members}
                    allMembers={allMembers}
                    labels={labels}
                    can={can}
                    boardTasks={boardTaskOptions}
                    onClose={() => setOpenTask(null)}
                />
            )}
            {blockedMove && <DependencyOverrideDialog move={blockedMove} onClose={() => setBlockedMove(null)} />}
            {columnDialog && (
                <ColumnDialog boardId={board.id} column={columnDialog === 'new' ? undefined : columnDialog} onClose={() => setColumnDialog(null)} />
            )}
            {approvalBlockedMessage && (
                <Dialog open onOpenChange={(open) => !open && setApprovalBlockedMessage(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Task is awaiting approval</DialogTitle>
                        </DialogHeader>
                        <p className="text-muted-foreground text-sm">{approvalBlockedMessage}</p>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
