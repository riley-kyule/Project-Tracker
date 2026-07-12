import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

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
};

const healthVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
    on_track: 'default',
    at_risk: 'secondary',
    off_track: 'destructive',
};

export default function ProjectShow({
    project,
    tasks,
    canManage,
}: {
    project: ProjectDetail;
    tasks: ProjectTask[];
    countries: Option[];
    websites: Option[];
    canManage: boolean;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
    ];

    const updateField = (field: string, value: string) => {
        router.patch(`/projects/${project.id}`, { [field]: value }, { preserveScroll: true });
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
                        <span className="text-muted-foreground">Department:</span> {project.department?.name ?? 'Company-wide'}
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
                                        <span className="text-muted-foreground ml-auto text-xs">
                                            {new Date(task.due_at).toLocaleDateString()}
                                        </span>
                                    )
                                )}
                            </li>
                        ))}
                        {tasks.length === 0 && <li className="text-muted-foreground py-2 text-sm">No tasks linked to this project yet.</li>}
                    </ul>
                </div>
            </div>
        </AppLayout>
    );
}
