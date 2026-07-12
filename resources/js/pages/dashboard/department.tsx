import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Person = { id: number; name: string; job_title?: string | null };

type DeptTask = {
    id: number;
    task_number: number;
    title: string;
    due_at: string | null;
    completed_at?: string | null;
    board: { id: number; name: string };
    assignee?: Person | null;
};

function StatCard({ label, value, alert = false }: { label: string; value: number; alert?: boolean }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div className={`text-2xl font-semibold ${alert && value > 0 ? 'text-destructive' : ''}`}>{value}</div>
            <div className="text-muted-foreground text-sm">{label}</div>
        </div>
    );
}

function TaskList({ title, tasks }: { title: string; tasks: DeptTask[] }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h2 className="mb-2 text-sm font-semibold">{title}</h2>
            <ul className="divide-sidebar-border/40 dark:divide-sidebar-border/40 divide-y">
                {tasks.map((task) => (
                    <li key={task.id} className="flex flex-wrap items-center gap-2 py-1.5 text-sm">
                        <Link href={`/boards/${task.board.id}`} className="font-medium hover:underline">
                            {task.title}
                        </Link>
                        {task.assignee && <span className="text-muted-foreground text-xs">{task.assignee.name}</span>}
                        {task.due_at && !task.completed_at && (
                            <span className="text-muted-foreground ml-auto text-xs">{new Date(task.due_at).toLocaleDateString()}</span>
                        )}
                        {task.completed_at && (
                            <span className="text-muted-foreground ml-auto text-xs">done {new Date(task.completed_at).toLocaleDateString()}</span>
                        )}
                    </li>
                ))}
                {tasks.length === 0 && <li className="text-muted-foreground py-1.5 text-sm">Nothing here.</li>}
            </ul>
        </div>
    );
}

export default function DepartmentDashboard({
    department,
    counts,
    workload,
    unassigned,
    upcoming,
    recentlyCompleted,
}: {
    department: { id: number; name: string };
    counts: { open: number; unassigned: number; overdue: number; blocked: number; awaiting_review: number; open_tickets: number };
    workload: (Person & { open_tasks: number; overdue_tasks: number })[];
    unassigned: DeptTask[];
    upcoming: DeptTask[];
    recentlyCompleted: DeptTask[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: `${department.name} Dashboard`, href: '/dashboards/department' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${department.name} Dashboard`} />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{department.name}</h1>

                <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <StatCard label="Open tasks" value={counts.open} />
                    <StatCard label="Unassigned" value={counts.unassigned} alert />
                    <StatCard label="Overdue" value={counts.overdue} alert />
                    <StatCard label="Blocked" value={counts.blocked} alert />
                    <StatCard label="Awaiting review" value={counts.awaiting_review} />
                    <StatCard label="Open tickets" value={counts.open_tickets} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border p-4">
                        <h2 className="mb-2 text-sm font-semibold">Workload by employee</h2>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground text-left">
                                    <th className="py-1.5 font-medium">Employee</th>
                                    <th className="py-1.5 text-right font-medium">Open</th>
                                    <th className="py-1.5 text-right font-medium">Overdue</th>
                                </tr>
                            </thead>
                            <tbody>
                                {workload.map((person) => (
                                    <tr key={person.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                        <td className="py-1.5">
                                            {person.name}
                                            {person.job_title && <span className="text-muted-foreground ml-1 text-xs">{person.job_title}</span>}
                                        </td>
                                        <td className="py-1.5 text-right">{person.open_tasks}</td>
                                        <td className={`py-1.5 text-right ${person.overdue_tasks > 0 ? 'text-destructive font-semibold' : ''}`}>
                                            {person.overdue_tasks}
                                        </td>
                                    </tr>
                                ))}
                                {workload.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="text-muted-foreground py-3 text-center">
                                            No active members in this department yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <TaskList title="Unassigned tasks" tasks={unassigned} />
                    <TaskList title="Upcoming deadlines (7 days)" tasks={upcoming} />
                    <TaskList title="Recently completed" tasks={recentlyCompleted} />
                </div>
            </div>
        </AppLayout>
    );
}
