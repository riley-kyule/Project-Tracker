import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type Option = { id: number; name: string };

type ProjectTask = {
    id: number;
    task_number: number;
    title: string;
    due_at: string | null;
    completed_at: string | null;
    board: { id: number; name: string };
    assignee: Option | null;
};

type ProjectDetail = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    health_status: string;
    priority: string;
    progress_percentage: number;
    deadline: string | null;
    department: Option | null;
    owner: Option;
    countries: Option[];
    websites: Option[];
    boards: { id: number; name: string }[];
    members: Option[];
    departments: Option[];
};

const healthVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
    on_track: 'default',
    at_risk: 'secondary',
    off_track: 'destructive',
};

const NO_SELECTION = 'none';

export default function ProjectShow({
    project,
    tasks,
    allUsers,
    allDepartments,
    unlinkedTasks,
    canManage,
    canDelete,
}: {
    project: ProjectDetail;
    tasks: ProjectTask[];
    countries: Option[];
    websites: Option[];
    allUsers: Option[];
    allDepartments: Option[];
    unlinkedTasks: { id: number; title: string; task_number: number }[];
    canManage: boolean;
    canDelete: boolean;
}) {
    const [newMemberId, setNewMemberId] = useState(NO_SELECTION);
    const [newDepartmentId, setNewDepartmentId] = useState(NO_SELECTION);
    const [taskToLinkId, setTaskToLinkId] = useState(NO_SELECTION);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
    ];

    const onError = (errors: Record<string, string>) => toast.error(Object.values(errors)[0] ?? 'That action failed.');

    const updateField = (field: string, value: string) => {
        router.patch(`/projects/${project.id}`, { [field]: value }, { preserveScroll: true, onError });
    };

    const addMember = () => {
        if (newMemberId === NO_SELECTION) return;
        router.patch(
            `/projects/${project.id}`,
            { member_ids: [...project.members.map((m) => m.id), Number(newMemberId)] },
            { preserveScroll: true, onError, onSuccess: () => setNewMemberId(NO_SELECTION) },
        );
    };

    const removeMember = (userId: number) => {
        router.patch(
            `/projects/${project.id}`,
            { member_ids: project.members.filter((m) => m.id !== userId).map((m) => m.id) },
            { preserveScroll: true, onError },
        );
    };

    const addDepartment = () => {
        if (newDepartmentId === NO_SELECTION) return;
        router.patch(
            `/projects/${project.id}`,
            { department_ids: [...project.departments.map((d) => d.id), Number(newDepartmentId)] },
            { preserveScroll: true, onError, onSuccess: () => setNewDepartmentId(NO_SELECTION) },
        );
    };

    const removeDepartment = (departmentId: number) => {
        router.patch(
            `/projects/${project.id}`,
            { department_ids: project.departments.filter((d) => d.id !== departmentId).map((d) => d.id) },
            { preserveScroll: true, onError },
        );
    };

    const linkTask = () => {
        if (taskToLinkId === NO_SELECTION) return;
        router.patch(
            `/tasks/${taskToLinkId}`,
            { project_id: project.id },
            { preserveScroll: true, onError, onSuccess: () => setTaskToLinkId(NO_SELECTION) },
        );
    };

    const unlinkTask = (taskId: number) => {
        router.patch(`/tasks/${taskId}`, { project_id: null }, { preserveScroll: true, onError });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">{project.name}</h1>
                    <Badge variant={healthVariant[project.health_status]}>{project.health_status.replace('_', ' ')}</Badge>
                    <Badge variant="secondary" className="capitalize">
                        {project.status.replace('_', ' ')}
                    </Badge>
                    {canDelete && (
                        <Button
                            size="sm"
                            variant="destructive"
                            className="ml-auto"
                            onClick={() => {
                                if (!confirm(`Delete "${project.name}"? This cannot be undone from the UI.`)) return;
                                router.delete(`/projects/${project.id}`);
                            }}
                        >
                            Delete project
                        </Button>
                    )}
                </div>

                {canManage && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={project.status} onValueChange={(value) => updateField('status', value)}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="planned">Planned</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="on_hold">On hold</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={project.health_status} onValueChange={(value) => updateField('health_status', value)}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="on_track">On track</SelectItem>
                                <SelectItem value="at_risk">At risk</SelectItem>
                                <SelectItem value="off_track">Off track</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                )}

                <div className="border-sidebar-border/70 dark:border-sidebar-border grid gap-3 rounded-xl border p-4 text-sm sm:grid-cols-2">
                    <div>
                        <span className="text-muted-foreground">Owner:</span> {project.owner.name}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Home department:</span> {project.department?.name ?? 'Company-wide'}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Deadline:</span>{' '}
                        {project.deadline ? new Date(project.deadline).toLocaleDateString() : '—'}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Progress:</span> {project.progress_percentage}%
                    </div>
                    {project.countries.length > 0 && (
                        <div className="sm:col-span-2">
                            <span className="text-muted-foreground">Countries:</span> {project.countries.map((c) => c.name).join(', ')}
                        </div>
                    )}
                    {project.websites.length > 0 && (
                        <div className="sm:col-span-2">
                            <span className="text-muted-foreground">Websites:</span> {project.websites.map((w) => w.name).join(', ')}
                        </div>
                    )}
                    {project.boards.length > 0 && (
                        <div className="sm:col-span-2">
                            <span className="text-muted-foreground">Boards:</span>{' '}
                            {project.boards.map((board, i) => (
                                <span key={board.id}>
                                    {i > 0 && ', '}
                                    <Link href={`/boards/${board.id}`} className="text-brand-600 dark:text-brand-400 hover:underline">
                                        {board.name}
                                    </Link>
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {project.description && (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                        <h2 className="mb-2 text-sm font-semibold">Description</h2>
                        <p className="text-sm whitespace-pre-wrap">{project.description}</p>
                    </div>
                )}

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-1 text-sm font-semibold">People</h2>
                    <p className="text-muted-foreground mb-2 text-xs">
                        Beyond the owner above — anyone can be added here, regardless of department.
                    </p>
                    <ul className="mb-2 space-y-1.5">
                        {project.members.map((member) => (
                            <li key={member.id} className="flex items-center gap-2 text-sm">
                                {member.name}
                                {canManage && (
                                    <button
                                        type="button"
                                        aria-label={`Remove ${member.name}`}
                                        onClick={() => removeMember(member.id)}
                                        className="text-muted-foreground hover:text-destructive ml-auto"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </li>
                        ))}
                        {project.members.length === 0 && <li className="text-muted-foreground text-sm">No additional people yet.</li>}
                    </ul>
                    {canManage && (
                        <div className="flex gap-2">
                            <Select value={newMemberId} onValueChange={setNewMemberId}>
                                <SelectTrigger className="h-8 flex-1 text-sm">
                                    <SelectValue placeholder="Add a person…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {allUsers
                                        .filter((user) => !project.members.some((m) => m.id === user.id) && user.id !== project.owner.id)
                                        .map((user) => (
                                            <SelectItem key={user.id} value={user.id.toString()}>
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            <Button type="button" size="sm" variant="secondary" disabled={newMemberId === NO_SELECTION} onClick={addMember}>
                                Add
                            </Button>
                        </div>
                    )}
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-1 text-sm font-semibold">Departments</h2>
                    <p className="text-muted-foreground mb-2 text-xs">Additional whole departments involved, beyond the home department above.</p>
                    <ul className="mb-2 space-y-1.5">
                        {project.departments.map((department) => (
                            <li key={department.id} className="flex items-center gap-2 text-sm">
                                {department.name}
                                {canManage && (
                                    <button
                                        type="button"
                                        aria-label={`Remove ${department.name}`}
                                        onClick={() => removeDepartment(department.id)}
                                        className="text-muted-foreground hover:text-destructive ml-auto"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </li>
                        ))}
                        {project.departments.length === 0 && <li className="text-muted-foreground text-sm">No additional departments yet.</li>}
                    </ul>
                    {canManage && (
                        <div className="flex gap-2">
                            <Select value={newDepartmentId} onValueChange={setNewDepartmentId}>
                                <SelectTrigger className="h-8 flex-1 text-sm">
                                    <SelectValue placeholder="Add a department…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {allDepartments
                                        .filter((department) => !project.departments.some((d) => d.id === department.id))
                                        .map((department) => (
                                            <SelectItem key={department.id} value={department.id.toString()}>
                                                {department.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            <Button type="button" size="sm" variant="secondary" disabled={newDepartmentId === NO_SELECTION} onClick={addDepartment}>
                                Add
                            </Button>
                        </div>
                    )}
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Tasks</h2>
                    <ul className="divide-sidebar-border/40 dark:divide-sidebar-border/40 divide-y">
                        {tasks.map((task) => (
                            <li key={task.id} className="flex flex-wrap items-center gap-2 py-2 text-sm">
                                <span className="text-muted-foreground font-mono text-xs">T-{task.task_number}</span>
                                <Link href={`/boards/${task.board.id}`} className="font-medium hover:underline">
                                    {task.title}
                                </Link>
                                <span className="text-muted-foreground text-xs">{task.assignee?.name ?? 'Unassigned'}</span>
                                {task.completed_at ? (
                                    <Badge variant="outline" className="ml-auto">
                                        Done
                                    </Badge>
                                ) : (
                                    task.due_at && (
                                        <span className="text-muted-foreground ml-auto text-xs">{new Date(task.due_at).toLocaleDateString()}</span>
                                    )
                                )}
                                {canManage && (
                                    <button
                                        type="button"
                                        aria-label={`Unlink ${task.title}`}
                                        onClick={() => unlinkTask(task.id)}
                                        className="text-muted-foreground hover:text-destructive"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </li>
                        ))}
                        {tasks.length === 0 && <li className="text-muted-foreground py-2 text-sm">No tasks linked to this project yet.</li>}
                    </ul>
                    {canManage && (
                        <div className="mt-2 flex gap-2">
                            <Select value={taskToLinkId} onValueChange={setTaskToLinkId}>
                                <SelectTrigger className="h-8 flex-1 text-sm">
                                    <SelectValue placeholder="Link an existing task…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {unlinkedTasks.map((candidate) => (
                                        <SelectItem key={candidate.id} value={candidate.id.toString()}>
                                            T-{candidate.task_number} {candidate.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button type="button" size="sm" variant="secondary" disabled={taskToLinkId === NO_SELECTION} onClick={linkTask}>
                                Link
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
