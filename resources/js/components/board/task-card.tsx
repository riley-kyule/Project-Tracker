import { TaskCollaboration } from '@/components/board/task-collaboration';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useForm } from '@inertiajs/react';
import { Calendar, Star } from 'lucide-react';

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

export function TaskCard({ task, onOpen, overlay = false }: { task: BoardTask; onOpen?: (task: BoardTask) => void; overlay?: boolean }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: task.id, disabled: overlay });

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            {...attributes}
            {...listeners}
            onClick={() => onOpen?.(task)}
            className={`bg-background border-sidebar-border/70 dark:border-sidebar-border cursor-grab rounded-lg border p-3 text-left shadow-sm ${
                isDragging ? 'opacity-40' : ''
            } ${overlay ? 'rotate-2 shadow-lg' : ''}`}
        >
            <div className="flex items-start gap-2">
                <span className="text-sm leading-snug font-medium">{task.title}</span>
                {task.ceo_priority && <Star className="ml-auto size-4 shrink-0 fill-amber-400 text-amber-400" aria-label="CEO priority" />}
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
                        <Calendar className="size-3" />
                        {new Date(task.due_at).toLocaleDateString()}
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

export function TaskDialog({
    task,
    members,
    labels,
    can,
    onClose,
}: {
    task: BoardTask;
    members: Member[];
    labels: LabelOption[];
    can: Can;
    onClose: () => void;
}) {
    const { data, setData, patch, processing, errors, transform } = useForm({
        title: task.title,
        description: task.description ?? '',
        primary_assignee_id: task.assignee?.id.toString() ?? NONE,
        priority: task.priority,
        due_at: task.due_at?.slice(0, 10) ?? '',
        progress_percentage: task.progress_percentage,
        ceo_priority: task.ceo_priority,
        label_ids: task.labels.map((label) => label.id),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            primary_assignee_id: form.primary_assignee_id === NONE ? null : Number(form.primary_assignee_id),
            due_at: form.due_at === '' ? null : form.due_at,
        }));
        patch(`/tasks/${task.id}`, { preserveScroll: true, onSuccess: onClose });
    };

    const toggleLabel = (id: number, checked: boolean) => {
        setData('label_ids', checked ? [...data.label_ids, id] : data.label_ids.filter((labelId) => labelId !== id));
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        <span className="text-muted-foreground mr-2 text-sm">T-{task.task_number}</span>
                        {task.title}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="task-title">Title</Label>
                        <Input id="task-title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="task-description">Description</Label>
                        <textarea
                            id="task-description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={4}
                            className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Assignee</Label>
                            <Select value={data.primary_assignee_id} onValueChange={(value) => setData('primary_assignee_id', value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>Unassigned</SelectItem>
                                    {members.map((member) => (
                                        <SelectItem key={member.id} value={member.id.toString()}>
                                            {member.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.primary_assignee_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Priority</Label>
                            <Select value={data.priority} onValueChange={(value) => setData('priority', value as BoardTask['priority'])}>
                                <SelectTrigger>
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
                            <Input id="task-due" type="date" value={data.due_at} onChange={(e) => setData('due_at', e.target.value)} />
                            <InputError message={errors.due_at} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="task-progress">Progress: {data.progress_percentage}%</Label>
                            <input
                                id="task-progress"
                                type="range"
                                min={0}
                                max={100}
                                step={5}
                                value={data.progress_percentage}
                                onChange={(e) => setData('progress_percentage', Number(e.target.value))}
                                className="accent-brand-600"
                            />
                            <InputError message={errors.progress_percentage} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Labels</Label>
                        <div className="flex flex-wrap gap-3">
                            {labels.map((label) => (
                                <label key={label.id} className="flex items-center gap-1.5 text-sm">
                                    <Checkbox
                                        checked={data.label_ids.includes(label.id)}
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
                                checked={data.ceo_priority}
                                onCheckedChange={(checked) => setData('ceo_priority', checked === true)}
                            />
                            <Label htmlFor="ceo-priority" className="flex items-center gap-1">
                                <Star className="size-4 fill-amber-400 text-amber-400" /> CEO Priority
                            </Label>
                        </div>
                    )}
                    <Button type="submit" disabled={processing}>
                        Save changes
                    </Button>
                </form>
                <div className="border-t pt-4">
                    <TaskCollaboration taskId={task.id} members={members} />
                </div>
            </DialogContent>
        </Dialog>
    );
}
