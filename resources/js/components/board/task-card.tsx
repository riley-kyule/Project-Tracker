import { TaskCollaboration } from '@/components/board/task-collaboration';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DateField } from '@/components/ui/date-field';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAutosave, type SaveStatus } from '@/hooks/use-autosave';
import { fmtDate } from '@/lib/utils';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { AlertCircle, Calendar, Check, CloudOff, Loader2, Lock, Star } from 'lucide-react';
import { useState } from 'react';

export type Member = { id: number; name: string };
export type LabelOption = { id: number; name: string; color: string };
export type Can = { manage: boolean; createTask: boolean; flagCeoPriority: boolean };

export type BoardTask = {
    id: number;
    task_number: number;
    title: string;
    description: string | null;
    priority: 'critical' | 'high' | 'medium' | 'low';
    start_date: string | null;
    due_at: string | null;
    progress_percentage: number;
    ceo_priority: boolean;
    work_location: string;
    board_column_id: number;
    position: number;
    assignee: Member | null;
    labels: LabelOption[];
    unresolved_dependencies_count?: number;
    checklist_items_count?: number;
    completed_checklist_items_count?: number;
};

const priorityStyles: Record<BoardTask['priority'], string> = {
    critical: 'bg-red-600 text-white',
    high: 'bg-orange-500 text-white',
    medium: 'bg-brand-600 text-white',
    low: 'bg-slate-400 text-white dark:bg-slate-600',
};

const NONE = 'none';

function initials(name: string) {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function overdue(task: BoardTask) {
    return task.due_at !== null && new Date(task.due_at) < new Date() && task.progress_percentage < 100;
}

export type ColumnOption = { id: number; name: string };

export function TaskCard({
    task,
    onOpen,
    overlay = false,
    selected,
    onToggleSelect,
    pending,
}: {
    task: BoardTask;
    onOpen?: (task: BoardTask) => void;
    overlay?: boolean;
    selected?: boolean;
    onToggleSelect?: (taskId: number, checked: boolean) => void;
    pending?: boolean;
}) {
    // The whole card is the drag surface — no separate grip handle. A plain
    // click still opens the task: PointerSensor in boards/show.tsx has a 6px
    // activation distance, so a click without movement never starts a drag.
    // For the keyboard we keep the split meaning: Enter opens, Space is handed
    // to dnd-kit to pick up / drop (arrows move — see its KeyboardSensor).
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: task.id,
        disabled: overlay,
    });

    const open = () => onOpen?.(task);

    // The card's first label doubles as its "cover" — a colored top strip for
    // at-a-glance categorization, the way Trello/ClickUp use a card cover —
    // without a redundant color field duplicating what labels already carry.
    const coverColor = task.labels[0]?.color;

    return (
        <div
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
                ...(coverColor ? { borderTopColor: coverColor, borderTopWidth: '4px' } : {}),
            }}
            {...(overlay ? {} : attributes)}
            {...(overlay ? {} : listeners)}
            role="button"
            tabIndex={overlay ? undefined : 0}
            aria-label={overlay ? undefined : `${task.title} — click to open, space to reorder`}
            onClick={open}
            onKeyDown={(e) => {
                if (e.target !== e.currentTarget) return;
                if (e.key === 'Enter') {
                    e.preventDefault();
                    open();
                    return;
                }
                if (e.key === ' ') {
                    // Hand off to dnd-kit's KeyboardSensor: Space picks up / drops.
                    listeners?.onKeyDown?.(e);
                }
            }}
            className={`bg-background border-sidebar-border/70 dark:border-sidebar-border focus-visible:ring-ring cursor-grab rounded-lg border p-3 text-left shadow-sm focus-visible:ring-2 focus-visible:outline-none active:cursor-grabbing ${
                isDragging ? 'opacity-40' : pending ? 'opacity-60' : ''
            } ${overlay ? 'rotate-2 shadow-lg' : ''}`}
        >
            <div className="flex items-start gap-2">
                {!overlay && onToggleSelect && (
                    <Checkbox
                        checked={selected ?? false}
                        onClick={(e) => e.stopPropagation()}
                        onCheckedChange={(checked) => onToggleSelect(task.id, checked === true)}
                        aria-label={`Select ${task.title}`}
                        className="mt-0.5 shrink-0"
                    />
                )}
                <span className="text-sm leading-snug font-medium">{task.title}</span>
                <span className="ml-auto flex shrink-0 items-center gap-1">
                    {(task.unresolved_dependencies_count ?? 0) > 0 && (
                        <Lock className="text-muted-foreground size-3.5" aria-label="Blocked by prerequisites" />
                    )}
                    {task.ceo_priority && <Star className="size-4 fill-amber-400 text-amber-400" aria-label="CEO priority" />}
                </span>
            </div>
            {task.labels.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1">
                    {task.labels.map((label) => (
                        <span key={label.id} className="rounded-full px-2 py-0.5 text-[10px] text-white" style={{ backgroundColor: label.color }}>
                            {label.name}
                        </span>
                    ))}
                </div>
            )}
            <div className="mt-2 flex items-center gap-2">
                <span className={`rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase ${priorityStyles[task.priority]}`}>{task.priority}</span>
                {task.due_at && (
                    <span className={`flex items-center gap-1 text-xs ${overdue(task) ? 'text-destructive font-semibold' : 'text-muted-foreground'}`}>
                        {overdue(task) ? <AlertCircle className="size-3" aria-label="Overdue" /> : <Calendar className="size-3" />}
                        {fmtDate(task.due_at)}
                    </span>
                )}
                {task.assignee && (
                    <span
                        title={task.assignee.name}
                        className="bg-brand-600 ml-auto flex size-6 items-center justify-center rounded-full text-[10px] font-semibold text-white"
                    >
                        {initials(task.assignee.name)}
                    </span>
                )}
            </div>
        </div>
    );
}

function SaveIndicator({ status, onRetry }: { status: SaveStatus; onRetry: () => void }) {
    if (status === 'idle') return null;
    if (status === 'saving') {
        return (
            <span className="text-muted-foreground ml-auto flex items-center gap-1 text-xs font-normal">
                <Loader2 className="size-3 animate-spin" /> Saving…
            </span>
        );
    }
    if (status === 'saved') {
        return (
            <span className="ml-auto flex items-center gap-1 text-xs font-normal text-emerald-600 dark:text-emerald-400">
                <Check className="size-3" /> Saved
            </span>
        );
    }
    return (
        <button type="button" onClick={onRetry} className="text-destructive ml-auto flex items-center gap-1 text-xs font-normal hover:underline">
            <CloudOff className="size-3" /> Couldn&rsquo;t save — retry
        </button>
    );
}

export function TaskDialog({
    task,
    members,
    allMembers,
    labels,
    can,
    boardTasks,
    columns,
    onMove,
    onClose,
}: {
    task: BoardTask;
    members: Member[];
    allMembers: Member[];
    labels: LabelOption[];
    can: Can;
    boardTasks: { id: number; title: string; task_number: number }[];
    columns: ColumnOption[];
    onMove: (taskId: number, columnId: number) => void;
    onClose: () => void;
}) {
    const [checklistCounts, setChecklistCounts] = useState({
        total: task.checklist_items_count ?? 0,
        completed: task.completed_checklist_items_count ?? 0,
    });

    // Real-time auto-save — no "Save changes" button. Text fields ride the
    // debounce; selects / checkboxes / the slider save immediately; blur and
    // dialog-close flush anything still pending.
    const { status, errors, save, flush } = useAutosave(`/tasks/${task.id}`);

    const [form, setForm] = useState({
        title: task.title,
        description: task.description ?? '',
        primary_assignee_id: task.assignee?.id.toString() ?? NONE,
        priority: task.priority as BoardTask['priority'],
        due_at: task.due_at?.slice(0, 10) ?? '',
        // Defaults to end of day rather than midnight — a task due "today" with no
        // explicit time shouldn't read as overdue the instant the clock passes 00:00.
        due_time: task.due_at && task.due_at.length > 10 ? task.due_at.slice(11, 16) : '23:59',
        progress_percentage: task.progress_percentage,
        ceo_priority: task.ceo_priority,
        label_ids: task.labels.map((label) => label.id),
    });

    const setField = <K extends keyof typeof form>(key: K, value: (typeof form)[K]) => setForm((prev) => ({ ...prev, [key]: value }));

    const saveTitle = (value: string) => {
        setField('title', value);
        // Title is required — keep the draft on screen, don't push an empty one.
        if (value.trim() !== '') save({ title: value });
    };

    const saveDue = (date: string, time: string) => {
        setForm((prev) => ({ ...prev, due_at: date, due_time: time }));
        save({ due_at: date === '' ? null : `${date}T${time || '23:59'}` }, { immediate: true });
    };

    const setProgress = (value: number) => {
        setField('progress_percentage', value);
        save({ progress_percentage: value }, { immediate: true });
    };

    const toggleLabel = (id: number, checked: boolean) => {
        const next = checked ? [...form.label_ids, id] : form.label_ids.filter((labelId) => labelId !== id);
        setField('label_ids', next);
        save({ label_ids: next }, { immediate: true });
    };

    const markCompleted = () => {
        setField('progress_percentage', 100);
        save({ progress_percentage: 100 }, { immediate: true });
        onClose();
    };

    const close = () => {
        flush();
        onClose();
    };

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <span className="text-muted-foreground text-sm">T-{task.task_number}</span>
                        <span>{form.title || task.title}</span>
                        <SaveIndicator status={status} onRetry={flush} />
                    </DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="task-title">Title</Label>
                        <Input id="task-title" value={form.title} onChange={(e) => saveTitle(e.target.value)} onBlur={flush} required />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="task-description">Description</Label>
                        <textarea
                            id="task-description"
                            value={form.description}
                            onChange={(e) => {
                                setField('description', e.target.value);
                                save({ description: e.target.value });
                            }}
                            onBlur={flush}
                            rows={4}
                            className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="task-assignee">Assignee</Label>
                            <Select
                                value={form.primary_assignee_id}
                                onValueChange={(value) => {
                                    setField('primary_assignee_id', value);
                                    save({ primary_assignee_id: value === NONE ? null : Number(value) }, { immediate: true });
                                }}
                            >
                                <SelectTrigger id="task-assignee">
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Unassigned</SelectItem>
                                    {/* Not board-scoped, same reasoning as allMembers everywhere else — assigning
                                        someone outside this board's department is itself how they get access to it. */}
                                    {allMembers.map((member) => (
                                        <SelectItem key={member.id} value={member.id.toString()}>
                                            {member.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.primary_assignee_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="task-priority">Priority</Label>
                            <Select
                                value={form.priority}
                                onValueChange={(value) => {
                                    setField('priority', value as BoardTask['priority']);
                                    save({ priority: value }, { immediate: true });
                                }}
                            >
                                <SelectTrigger id="task-priority">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="critical">Critical</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="low">Low</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.priority} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="task-due">Due date</Label>
                            <DateField id="task-due" value={form.due_at} onChange={(v) => saveDue(v, form.due_time)} />
                            <InputError message={errors.due_at} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="task-due-time">Due time</Label>
                            <Input
                                id="task-due-time"
                                type="time"
                                value={form.due_time}
                                disabled={form.due_at === ''}
                                onChange={(e) => saveDue(form.due_at, e.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="task-column">Column</Label>
                            <Select value={task.board_column_id.toString()} onValueChange={(value) => onMove(task.id, Number(value))}>
                                <SelectTrigger id="task-column">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {columns.map((column) => (
                                        <SelectItem key={column.id} value={column.id.toString()}>
                                            {column.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="task-progress">Progress</Label>
                            {checklistCounts.total > 0 ? (
                                <>
                                    <div className="bg-secondary h-2 w-full overflow-hidden rounded-full">
                                        <div className="bg-brand-600 h-full" style={{ width: `${form.progress_percentage}%` }} />
                                    </div>
                                    <span className="text-muted-foreground text-xs">
                                        {form.progress_percentage}% — {checklistCounts.completed} of {checklistCounts.total} checklist items done
                                    </span>
                                </>
                            ) : form.progress_percentage === 100 ? (
                                <div className="flex items-center gap-2">
                                    <Button type="button" size="sm" onClick={markCompleted} className="w-fit">
                                        Mark as Completed
                                    </Button>
                                    <button type="button" onClick={() => setProgress(90)} className="text-muted-foreground text-xs hover:underline">
                                        Not done yet?
                                    </button>
                                </div>
                            ) : (
                                <>
                                    <input
                                        id="task-progress"
                                        type="range"
                                        min={0}
                                        max={100}
                                        step={5}
                                        value={form.progress_percentage}
                                        onChange={(e) => setProgress(Number(e.target.value))}
                                        className="accent-brand-600"
                                    />
                                    <span className="text-muted-foreground text-xs">{form.progress_percentage}%</span>
                                </>
                            )}
                            <InputError message={errors.progress_percentage} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Labels</Label>
                        <div className="flex flex-wrap gap-3">
                            {labels.map((label) => (
                                <label key={label.id} className="flex items-center gap-1.5 text-sm">
                                    <Checkbox
                                        checked={form.label_ids.includes(label.id)}
                                        onCheckedChange={(checked) => toggleLabel(label.id, checked === true)}
                                    />
                                    <span className="rounded-full px-2 py-0.5 text-[11px] text-white" style={{ backgroundColor: label.color }}>
                                        {label.name}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                    {can.flagCeoPriority && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="ceo-priority"
                                checked={form.ceo_priority}
                                onCheckedChange={(checked) => {
                                    setField('ceo_priority', checked === true);
                                    save({ ceo_priority: checked === true }, { immediate: true });
                                }}
                            />
                            <Label htmlFor="ceo-priority" className="flex items-center gap-1">
                                <Star className="size-4 fill-amber-400 text-amber-400" /> CEO Priority
                            </Label>
                        </div>
                    )}
                </div>
                <div className="border-t pt-4">
                    <TaskCollaboration
                        taskId={task.id}
                        members={members}
                        allMembers={allMembers}
                        boardTasks={boardTasks}
                        canDuplicate={can.createTask}
                        onDeleted={onClose}
                        onChecklistProgressChange={(percentage, completed, total) => {
                            setChecklistCounts({ completed, total });
                            if (percentage !== null) setField('progress_percentage', percentage);
                        }}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}
