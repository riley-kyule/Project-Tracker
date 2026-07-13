import { TrafficDataSection } from '@/components/dashboard/traffic-data-section';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Star } from 'lucide-react';

type Person = { id: number; name: string };

type ExecTask = {
    id: number;
    task_number: number;
    title: string;
    priority: string;
    due_at: string | null;
    board: { id: number; name: string };
    assignee: Person | null;
};

type DeptRow = { id: number; name: string; open: number; overdue: number; completed_week: number };

type Activity = { id: number; event: string; auditable_type: string; actor: Person | null; created_at: string };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'CEO Dashboard', href: '/dashboards/ceo' }];

function StatCard({ label, value, href, alert = false }: { label: string; value: number; href?: string; alert?: boolean }) {
    const inner = (
        <div className="border-sidebar-border/70 dark:border-sidebar-border hover:border-brand-500 h-full rounded-xl border p-4 transition-colors">
            <div className={`text-2xl font-semibold ${alert && value > 0 ? 'text-destructive' : ''}`}>{value}</div>
            <div className="text-muted-foreground text-sm">{label}</div>
        </div>
    );

    return href ? <Link href={href}>{inner}</Link> : inner;
}

function TaskList({ title, tasks, icon }: { title: string; tasks: ExecTask[]; icon?: React.ReactNode }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h2 className="mb-2 flex items-center gap-1.5 text-sm font-semibold">
                {icon}
                {title}
            </h2>
            <ul className="divide-sidebar-border/40 dark:divide-sidebar-border/40 divide-y">
                {tasks.map((task) => (
                    <li key={task.id} className="flex flex-wrap items-center gap-2 py-1.5 text-sm">
                        <Link href={`/boards/${task.board.id}`} className="font-medium hover:underline">
                            {task.title}
                        </Link>
                        <span className="text-muted-foreground text-xs">{task.assignee?.name ?? 'Unassigned'}</span>
                        {task.due_at && <span className="text-muted-foreground ml-auto text-xs">{new Date(task.due_at).toLocaleDateString()}</span>}
                    </li>
                ))}
                {tasks.length === 0 && <li className="text-muted-foreground py-1.5 text-sm">Nothing here.</li>}
            </ul>
        </div>
    );
}

export default function CeoDashboard({
    counts,
    departmentPerformance,
    workload,
    ceoPriorityTasks,
    upcoming,
    recentActivity,
}: {
    counts: {
        due_today: number;
        overdue: number;
        blocked: number;
        awaiting_review: number;
        ceo_priority: number;
        completed_today: number;
        completed_week: number;
        critical_tickets: number;
        overdue_tickets: number;
    };
    departmentPerformance: DeptRow[];
    workload: (Person & { open_tasks: number })[];
    ceoPriorityTasks: ExecTask[];
    upcoming: ExecTask[];
    recentActivity: Activity[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="CEO Dashboard" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Company overview</h1>

                {/* Exceptions first (UI_UX_SPEC): what needs attention right now. */}
                <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <StatCard label="Overdue" value={counts.overdue} href="/reports/tasks?filter=overdue" alert />
                    <StatCard label="Blocked" value={counts.blocked} href="/reports/tasks?filter=blocked" alert />
                    <StatCard label="Awaiting review" value={counts.awaiting_review} href="/reports/tasks?filter=awaiting_review" />
                    <StatCard label="Due today" value={counts.due_today} href="/reports/tasks?filter=due_today" />
                    <StatCard label="CEO priority" value={counts.ceo_priority} href="/reports/tasks?filter=ceo_priority" />
                </div>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Completed today" value={counts.completed_today} />
                    <StatCard label="Completed this week" value={counts.completed_week} href="/reports/tasks?filter=completed_week" />
                    <StatCard label="Critical tickets" value={counts.critical_tickets} href="/tickets?priority=critical" alert />
                    <StatCard label="Overdue tickets" value={counts.overdue_tickets} href="/dashboards/it" alert />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border p-4">
                        <h2 className="mb-2 text-sm font-semibold">Department performance</h2>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground text-left">
                                    <th className="py-1.5 font-medium">Department</th>
                                    <th className="py-1.5 text-right font-medium">Open</th>
                                    <th className="py-1.5 text-right font-medium">Overdue</th>
                                    <th className="py-1.5 text-right font-medium">Done this week</th>
                                </tr>
                            </thead>
                            <tbody>
                                {departmentPerformance.map((department) => (
                                    <tr key={department.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                        <td className="py-1.5">
                                            <Link
                                                href={`/reports/tasks?department_id=${department.id}`}
                                                className="text-brand-600 dark:text-brand-400 hover:underline"
                                            >
                                                {department.name}
                                            </Link>
                                        </td>
                                        <td className="py-1.5 text-right">{department.open}</td>
                                        <td className={`py-1.5 text-right ${department.overdue > 0 ? 'text-destructive font-semibold' : ''}`}>
                                            {department.overdue}
                                        </td>
                                        <td className="py-1.5 text-right">{department.completed_week}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                        <h2 className="mb-2 text-sm font-semibold">Employee workload (open tasks)</h2>
                        <ul className="space-y-1.5">
                            {workload.map((person) => (
                                <li key={person.id} className="flex items-center gap-2 text-sm">
                                    <Link
                                        href={`/reports/tasks?assignee_id=${person.id}`}
                                        className="text-brand-600 dark:text-brand-400 hover:underline"
                                    >
                                        {person.name}
                                    </Link>
                                    <div className="bg-secondary ml-auto h-2 w-32 overflow-hidden rounded-full">
                                        <div className="bg-brand-600 h-full" style={{ width: `${Math.min(100, person.open_tasks * 10)}%` }} />
                                    </div>
                                    <span className="w-6 text-right font-medium">{person.open_tasks}</span>
                                </li>
                            ))}
                            {workload.length === 0 && <li className="text-muted-foreground text-sm">No assigned work yet.</li>}
                        </ul>
                    </div>

                    <TaskList title="CEO priority" tasks={ceoPriorityTasks} icon={<Star className="size-4 fill-amber-400 text-amber-400" />} />
                    <TaskList title="Upcoming deadlines (7 days)" tasks={upcoming} />
                </div>

                <TrafficDataSection />

                <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">Recent activity</h2>
                    <ul className="space-y-1">
                        {recentActivity.map((entry) => (
                            <li key={entry.id} className="text-muted-foreground text-xs">
                                <span className="text-foreground font-medium">{entry.actor?.name ?? 'System'}</span> {entry.event}{' '}
                                <Badge variant="outline" className="ml-1 text-[10px]">
                                    {entry.auditable_type.split('\\').pop()}
                                </Badge>
                                {' · '}
                                {new Date(entry.created_at).toLocaleString()}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AppLayout>
    );
}
