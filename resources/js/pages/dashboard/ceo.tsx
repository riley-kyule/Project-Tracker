import { TrafficDataSection } from '@/components/dashboard/traffic-data-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { LayoutDashboard, Star } from 'lucide-react';
import { useMemo, useState } from 'react';

type WordPressStaffRow = {
    id: number;
    wordpress_site_id: number;
    username: string;
    email: string | null;
    display_name: string | null;
    roles: string[];
    site: { id: number; name: string; domain: string | null };
};

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

type DeptRow = { id: number; name: string; open: number; overdue: number; completed_week: number; completed_total: number };

type Activity = { id: number; event: string; auditable_type: string; actor: Person | null; created_at: string };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'CEO Dashboard', href: '/dashboards/ceo' }];

const QUICK_LINKS = [
    { href: '#department-performance', label: 'Department performance' },
    { href: '#employee-workload', label: 'Employee workload' },
    { href: '#ceo-priority', label: 'CEO priority' },
    { href: '#upcoming-deadlines', label: 'Upcoming deadlines' },
    { href: '#traffic-data', label: 'Traffic & marketing' },
    { href: '#wordpress-staff', label: 'WordPress staff' },
    { href: '#recent-activity', label: 'Recent activity' },
];

function QuickLinks() {
    return (
        <nav aria-label="Jump to section" className="flex flex-wrap gap-2">
            {QUICK_LINKS.map((link) => (
                <a
                    key={link.href}
                    href={link.href}
                    className="border-sidebar-border/70 dark:border-sidebar-border hover:border-brand-500 hover:text-brand-600 dark:hover:text-brand-400 rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                >
                    {link.label}
                </a>
            ))}
        </nav>
    );
}

function StatCard({ label, value, href, alert = false }: { label: string; value: number; href?: string; alert?: boolean }) {
    const inner = (
        <div className="border-sidebar-border/70 dark:border-sidebar-border hover:border-brand-500 h-full rounded-xl border p-4 transition-colors">
            <div className={`text-2xl font-semibold ${alert && value > 0 ? 'text-destructive' : ''}`}>{value}</div>
            <div className="text-muted-foreground text-sm">{label}</div>
        </div>
    );

    return href ? <Link href={href}>{inner}</Link> : inner;
}

const ALL_WEBSITES = 'all';
const PAGE_SIZE = 25;

function WordPressStaffCard({ staff }: { staff: WordPressStaffRow[] }) {
    const [siteFilter, setSiteFilter] = useState(ALL_WEBSITES);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const sites = useMemo(() => {
        const seen = new Map<number, string>();
        staff.forEach((row) => seen.set(row.site.id, row.site.name));
        return Array.from(seen, ([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name));
    }, [staff]);

    const bySite = siteFilter === ALL_WEBSITES ? staff : staff.filter((row) => row.site.id === Number(siteFilter));

    const term = search.trim().toLowerCase();
    const filtered =
        term === ''
            ? bySite
            : bySite.filter(
                  (row) =>
                      (row.display_name ?? '').toLowerCase().includes(term) ||
                      row.username.toLowerCase().includes(term) ||
                      (row.email ?? '').toLowerCase().includes(term),
              );

    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const currentPage = Math.min(page, totalPages);
    const pageRows = filtered.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

    const changeSite = (value: string) => {
        setSiteFilter(value);
        setPage(1);
    };

    const changeSearch = (value: string) => {
        setSearch(value);
        setPage(1);
    };

    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-semibold">WordPress staff access</h2>
                <div className="flex flex-wrap items-center gap-2">
                    <Input placeholder="Search staff…" value={search} onChange={(e) => changeSearch(e.target.value)} className="h-8 w-40 text-xs" />
                    <Select value={siteFilter} onValueChange={changeSite}>
                        <SelectTrigger className="h-8 w-48 text-xs" aria-label="Filter by website">
                            <SelectValue placeholder="All websites" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_WEBSITES}>All websites</SelectItem>
                            {sites.map((site) => (
                                <SelectItem key={site.id} value={site.id.toString()}>
                                    {site.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Link href="/admin/wordpress-users" className="text-brand-600 dark:text-brand-400 text-xs hover:underline">
                        Manage →
                    </Link>
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-muted-foreground text-left">
                            <th className="py-1.5 font-medium">Staff</th>
                            <th className="py-1.5 font-medium">Website</th>
                            <th className="py-1.5 font-medium">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pageRows.map((row) => (
                            <tr key={row.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                <td className="py-1.5">
                                    <div className="font-medium">{row.display_name ?? row.username}</div>
                                    <div className="text-muted-foreground text-xs">{row.email}</div>
                                </td>
                                <td className="py-1.5">{row.site.name}</td>
                                <td className="py-1.5">
                                    <div className="flex flex-wrap gap-1">
                                        {row.roles.map((role) => (
                                            <Badge key={role} variant="secondary" className="capitalize">
                                                {role}
                                            </Badge>
                                        ))}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {pageRows.length === 0 && (
                            <tr>
                                <td colSpan={3} className="text-muted-foreground py-1.5">
                                    {staff.length === 0 ? 'No exotic-online.com staff found on any connected site.' : 'No staff match this filter.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            {totalPages > 1 && (
                <div className="mt-2 flex items-center justify-between text-xs">
                    <span className="text-muted-foreground">
                        Page {currentPage} of {totalPages} ({filtered.length} staff)
                    </span>
                    <div className="flex gap-1">
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-7 px-2 text-xs"
                            disabled={currentPage === 1}
                            onClick={() => setPage(currentPage - 1)}
                        >
                            Previous
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-7 px-2 text-xs"
                            disabled={currentPage === totalPages}
                            onClick={() => setPage(currentPage + 1)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

function TaskList({ id, title, tasks, icon }: { id?: string; title: string; tasks: ExecTask[]; icon?: React.ReactNode }) {
    return (
        <div id={id} className="border-sidebar-border/70 dark:border-sidebar-border scroll-mt-4 rounded-xl border p-4">
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
    wordpressStaff,
}: {
    counts: {
        due_today: number;
        overdue: number;
        blocked: number;
        awaiting_review: number;
        ceo_priority: number;
        completed_today: number;
        completed_week: number;
        completed_total: number;
        critical_tickets: number;
        overdue_tickets: number;
    };
    departmentPerformance: DeptRow[];
    workload: (Person & { open_tasks: number })[];
    ceoPriorityTasks: ExecTask[];
    upcoming: ExecTask[];
    recentActivity: Activity[];
    wordpressStaff: WordPressStaffRow[];
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
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <StatCard label="Completed today" value={counts.completed_today} />
                    <StatCard label="Completed this week" value={counts.completed_week} href="/reports/tasks?filter=completed_week" />
                    <StatCard label="Completed tasks" value={counts.completed_total} />
                    <StatCard label="Critical tickets" value={counts.critical_tickets} href="/tickets?priority=critical" alert />
                    <StatCard label="Overdue tickets" value={counts.overdue_tickets} href="/dashboards/it" alert />
                </div>

                <QuickLinks />

                <div className="grid gap-4 lg:grid-cols-2">
                    <div
                        id="department-performance"
                        className="border-sidebar-border/70 dark:border-sidebar-border scroll-mt-4 overflow-x-auto rounded-xl border p-4"
                    >
                        <h2 className="mb-2 text-sm font-semibold">Department performance</h2>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground text-left">
                                    <th className="py-1.5 font-medium">Department</th>
                                    <th className="py-1.5 text-right font-medium">Open</th>
                                    <th className="py-1.5 text-right font-medium">Overdue</th>
                                    <th className="py-1.5 text-right font-medium">Done this week</th>
                                    <th className="py-1.5 text-right font-medium">Total completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                {departmentPerformance.map((department) => (
                                    <tr key={department.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                        <td className="py-1.5">
                                            <div className="flex items-center gap-1.5">
                                                <Link
                                                    href={`/reports/tasks?department_id=${department.id}`}
                                                    className="text-brand-600 dark:text-brand-400 hover:underline"
                                                >
                                                    {department.name}
                                                </Link>
                                                <Link
                                                    href={`/dashboards/department?department_id=${department.id}`}
                                                    className="text-muted-foreground hover:text-brand-600 dark:hover:text-brand-400"
                                                    aria-label={`Open ${department.name} dashboard`}
                                                    title={`Open ${department.name} dashboard`}
                                                >
                                                    <LayoutDashboard className="size-3.5" />
                                                </Link>
                                            </div>
                                        </td>
                                        <td className="py-1.5 text-right">{department.open}</td>
                                        <td className={`py-1.5 text-right ${department.overdue > 0 ? 'text-destructive font-semibold' : ''}`}>
                                            {department.overdue}
                                        </td>
                                        <td className="py-1.5 text-right">{department.completed_week}</td>
                                        <td className="py-1.5 text-right">{department.completed_total}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div id="employee-workload" className="border-sidebar-border/70 dark:border-sidebar-border scroll-mt-4 rounded-xl border p-4">
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

                    <TaskList
                        id="ceo-priority"
                        title="CEO priority"
                        tasks={ceoPriorityTasks}
                        icon={<Star className="size-4 fill-amber-400 text-amber-400" />}
                    />
                    <TaskList id="upcoming-deadlines" title="Upcoming deadlines (7 days)" tasks={upcoming} />
                </div>

                <div id="traffic-data" className="scroll-mt-4">
                    <TrafficDataSection />
                </div>

                <div id="wordpress-staff" className="scroll-mt-4">
                    <WordPressStaffCard staff={wordpressStaff} />
                </div>

                <div id="recent-activity" className="border-sidebar-border/70 dark:border-sidebar-border scroll-mt-4 rounded-xl border p-4">
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
