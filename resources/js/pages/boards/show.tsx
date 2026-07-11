import { TaskCard, TaskDialog, type BoardTask, type Can, type LabelOption, type Member } from '@/components/board/task-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
import { Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type Column = {
    id: number;
    name: string;
    semantic_status: string;
    is_completion_column: boolean;
    wip_limit: number | null;
    tasks: BoardTask[];
};

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
    boardId,
    canCreate,
    onOpenTask,
}: {
    column: Column;
    boardId: number;
    canCreate: boolean;
    onOpenTask: (task: BoardTask) => void;
}) {
    const { setNodeRef } = useDroppable({ id: `column-${column.id}` });
    const overLimit = column.wip_limit !== null && column.tasks.length > column.wip_limit;

    return (
        <div className="bg-sidebar dark:bg-sidebar border-sidebar-border/70 dark:border-sidebar-border flex w-72 shrink-0 flex-col rounded-xl border">
            <div className="flex items-center justify-between p-3 pb-1">
                <span className="text-sm font-semibold">{column.name}</span>
                <span className={`text-xs ${overLimit ? 'text-destructive font-semibold' : 'text-muted-foreground'}`}>
                    {column.tasks.length}
                    {column.wip_limit !== null && ` / ${column.wip_limit}`}
                </span>
            </div>
            <SortableContext items={column.tasks.map((task) => task.id)} strategy={verticalListSortingStrategy}>
                <div ref={setNodeRef} className="flex min-h-16 flex-1 flex-col gap-2 p-2">
                    {column.tasks.map((task) => (
                        <TaskCard key={task.id} task={task} onOpen={onOpenTask} />
                    ))}
                </div>
            </SortableContext>
            {canCreate && <QuickAdd boardId={boardId} columnId={column.id} />}
        </div>
    );
}

export default function BoardShow({ board, members, labels, can }: { board: Board; members: Member[]; labels: LabelOption[]; can: Can }) {
    const [columns, setColumns] = useState<Column[]>(board.columns);
    const [activeTask, setActiveTask] = useState<BoardTask | null>(null);
    const [openTask, setOpenTask] = useState<BoardTask | null>(null);
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

    const findColumn = (taskId: number) => columns.find((column) => column.tasks.some((task) => task.id === taskId));

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

        router.post(`/tasks/${activeId}/move`, { board_column_id: column.id, position: index + 1 }, { preserveScroll: true, preserveState: true });
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
                        <Input placeholder="Search tasks…" value={search} onChange={(e) => setSearch(e.target.value)} className="w-48" />
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
                        {visibleColumns.map((column) => (
                            <BoardColumn
                                key={column.id}
                                column={column}
                                boardId={board.id}
                                canCreate={can.createTask && !filtering}
                                onOpenTask={setOpenTask}
                            />
                        ))}
                    </div>
                    <DragOverlay>{activeTask && <TaskCard task={activeTask} overlay />}</DragOverlay>
                </DndContext>
            </div>
            {openTask && <TaskDialog task={openTask} members={members} labels={labels} can={can} onClose={() => setOpenTask(null)} />}
        </AppLayout>
    );
}
