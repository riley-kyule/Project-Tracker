import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type WorkloadRow = {
    id: number;
    name: string;
    job_title: string | null;
    department: { id: number; name: string } | null;
    open_tasks: number;
    overdue_tasks: number;
    blocked_tasks: number;
    awaiting_review_tasks: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports/tasks' },
    { title: 'Workload', href: '/reports/workload' },
];

const ALL = 'all';

export default function WorkloadReport({
    people,
    departments,
    selected,
    canFilterDepartment,
}: {
    people: WorkloadRow[];
    departments: { id: number; name: string }[];
    selected: { department_id?: number | string | null };
    canFilterDepartment: boolean;
}) {
    const apply = (params: Record<string, string | undefined>) => {
        router.get(
            '/reports/workload',
            Object.fromEntries(
                Object.entries({ department_id: selected.department_id?.toString(), ...params }).filter(([, value]) => value && value !== ALL),
            ) as Record<string, string>,
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workload report" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-xl font-semibold">Workload &amp; exceptions</h1>
                    <span className="text-muted-foreground text-sm">{people.length} people</span>
                    <Link href="/reports/tasks" className="text-brand-600 dark:text-brand-400 text-sm hover:underline">
                        Task report →
                    </Link>
                    {canFilterDepartment && (
                        <div className="ml-auto flex flex-wrap gap-2">
                            <Select value={selected.department_id?.toString() ?? ALL} onValueChange={(value) => apply({ department_id: value })}>
                                <SelectTrigger className="w-48">
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
                        </div>
                    )}
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-sidebar-border/70 dark:border-sidebar-border border-b text-left">
                                <th className="p-3 font-medium">Employee</th>
                                <th className="p-3 font-medium">Department</th>
                                <th className="p-3 text-right font-medium">Open</th>
                                <th className="p-3 text-right font-medium">Overdue</th>
                                <th className="p-3 text-right font-medium">Blocked</th>
                                <th className="p-3 text-right font-medium">Awaiting review</th>
                            </tr>
                        </thead>
                        <tbody>
                            {people.map((person) => (
                                <tr key={person.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className="p-3 font-medium">
                                        {person.name}
                                        {person.job_title && (
                                            <span className="text-muted-foreground ml-1 text-xs font-normal">{person.job_title}</span>
                                        )}
                                    </td>
                                    <td className="p-3">{person.department?.name ?? '—'}</td>
                                    <td className="p-3 text-right">
                                        <Link
                                            href={`/reports/tasks?assignee_id=${person.id}`}
                                            className="text-brand-600 dark:text-brand-400 hover:underline"
                                        >
                                            {person.open_tasks}
                                        </Link>
                                    </td>
                                    <td className={`p-3 text-right ${person.overdue_tasks > 0 ? 'text-destructive font-semibold' : ''}`}>
                                        <Link
                                            href={`/reports/tasks?assignee_id=${person.id}&filter=overdue`}
                                            className={
                                                person.overdue_tasks > 0 ? 'hover:underline' : 'text-brand-600 dark:text-brand-400 hover:underline'
                                            }
                                        >
                                            {person.overdue_tasks}
                                        </Link>
                                    </td>
                                    <td className="p-3 text-right">
                                        <Link
                                            href={`/reports/tasks?assignee_id=${person.id}&filter=blocked`}
                                            className="text-brand-600 dark:text-brand-400 hover:underline"
                                        >
                                            {person.blocked_tasks}
                                        </Link>
                                    </td>
                                    <td className="p-3 text-right">
                                        <Link
                                            href={`/reports/tasks?assignee_id=${person.id}&filter=awaiting_review`}
                                            className="text-brand-600 dark:text-brand-400 hover:underline"
                                        >
                                            {person.awaiting_review_tasks}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {people.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground p-6 text-center">
                                        No active people in scope.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
