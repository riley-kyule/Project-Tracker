import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DateField } from '@/components/ui/date-field';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarCog, CalendarDays, ChevronLeft, ChevronRight, ListChecks, Plus } from 'lucide-react';
import { useState } from 'react';

type Ref = { id: number; name: string };

type PendingRow = {
    id: number;
    employee: string;
    type: string;
    code: string;
    status: string;
    days: number;
    start_date: string;
    end_date: string;
    is_emergency: boolean;
    reason: string | null;
};

type CalEvent = {
    id: number;
    employee: string;
    department_id: number | null;
    type: string;
    code: string;
    color: string | null;
    status: string;
    start_date: string;
    end_date: string;
};

type LeaveTypeOption = {
    id: number;
    name: string;
    code: string;
    is_emergency: boolean;
    requires_document: boolean;
    gender_eligibility: string | null;
};

type PageProps = {
    month: string;
    calendarRange: { from: string; to: string };
    pending: PendingRow[];
    calendarLeave: CalEvent[];
    holidays: string[];
    canManage: boolean;
    canFileOnBehalf: boolean;
    hasOwnRecord: boolean;
    leaveTypes: LeaveTypeOption[];
    employees: Ref[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Leave', href: '/hr/leave' }];

function ymd(d: Date) {
    return d.toISOString().slice(0, 10);
}

function DecideButtons({ request }: { request: PendingRow }) {
    const [rejecting, setRejecting] = useState(false);
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const act = (approve: boolean) => {
        setProcessing(true);
        router.post(`/hr/leave/requests/${request.id}/decision`, { approve, note }, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };

    return (
        <div className="flex flex-col items-end gap-1">
            <div className="flex gap-2">
                <Button size="sm" disabled={processing} onClick={() => act(true)}>
                    Approve
                </Button>
                <Button size="sm" variant="outline" disabled={processing} onClick={() => setRejecting((v) => !v)}>
                    Reject
                </Button>
            </div>
            {rejecting && (
                <div className="flex items-center gap-2">
                    <Input placeholder="Reason (optional)" value={note} onChange={(e) => setNote(e.target.value)} className="h-8 w-48" />
                    <Button size="sm" variant="destructive" disabled={processing} onClick={() => act(false)}>
                        Confirm reject
                    </Button>
                </div>
            )}
        </div>
    );
}

function FileLeaveDialog({ employees, leaveTypes }: { employees: Ref[]; leaveTypes: LeaveTypeOption[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        employee_id: '',
        leave_type_id: '',
        start_date: '',
        end_date: '',
        reason: '',
        is_emergency: false as boolean,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((f) => ({ ...f, employee_id: Number(f.employee_id), leave_type_id: Number(f.leave_type_id) }));
        post('/hr/leave/requests', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Plus className="mr-1 h-4 w-4" /> File for someone else
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>File a leave request on behalf of an employee</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="fl-emp">Employee</Label>
                        <Select value={data.employee_id} onValueChange={(v) => setData('employee_id', v)}>
                            <SelectTrigger id="fl-emp">
                                <SelectValue placeholder="Select employee" />
                            </SelectTrigger>
                            <SelectContent>
                                {employees.map((e) => (
                                    <SelectItem key={e.id} value={String(e.id)}>
                                        {e.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.employee_id} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="fl-type">Leave type</Label>
                        <Select value={data.leave_type_id} onValueChange={(v) => setData('leave_type_id', v)}>
                            <SelectTrigger id="fl-type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                {leaveTypes.map((t) => (
                                    <SelectItem key={t.id} value={String(t.id)}>
                                        {t.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.leave_type_id} />
                    </div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="fl-start">Start</Label>
                            <DateField id="fl-start" value={data.start_date} onChange={(v) => setData('start_date', v)} />
                            <InputError message={errors.start_date} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="fl-end">End</Label>
                            <DateField id="fl-end" value={data.end_date} onChange={(v) => setData('end_date', v)} />
                            <InputError message={errors.end_date} />
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="fl-reason">Reason</Label>
                        <Input id="fl-reason" value={data.reason} onChange={(e) => setData('reason', e.target.value)} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.is_emergency} onCheckedChange={(v) => setData('is_emergency', v === true)} />
                        Emergency leave (bypasses the same-department block)
                    </label>
                    <Button type="submit" disabled={processing}>
                        Submit
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function MonthCalendar({
    month,
    range,
    events,
    holidays,
}: {
    month: string;
    range: { from: string; to: string };
    events: CalEvent[];
    holidays: string[];
}) {
    const start = new Date(range.from + 'T00:00:00');
    const end = new Date(range.to + 'T00:00:00');
    const monthDate = new Date(month + 'T00:00:00');
    const days: Date[] = [];
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) days.push(new Date(d));

    const eventsOn = (day: string) => events.filter((e) => e.start_date <= day && e.end_date >= day);

    const prev = new Date(monthDate);
    prev.setMonth(prev.getMonth() - 1);
    const next = new Date(monthDate);
    next.setMonth(next.getMonth() + 1);

    return (
        <Card className="p-3">
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-sm font-semibold">{monthDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</h2>
                <div className="flex gap-1">
                    <Link href={`/hr/leave?month=${ymd(prev)}`} preserveScroll className="hover:bg-muted rounded p-1">
                        <ChevronLeft className="h-4 w-4" />
                    </Link>
                    <Link href={`/hr/leave?month=${ymd(next)}`} preserveScroll className="hover:bg-muted rounded p-1">
                        <ChevronRight className="h-4 w-4" />
                    </Link>
                </div>
            </div>
            <div className="overflow-x-auto">
                <div className="grid min-w-[36rem] grid-cols-7 gap-px text-xs">
                    {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => (
                        <div key={d} className="text-muted-foreground p-1 text-center font-medium">
                            {d}
                        </div>
                    ))}
                    {days.map((d) => {
                        const key = ymd(d);
                        const inMonth = d.getMonth() === monthDate.getMonth();
                        const isHoliday = holidays.includes(key);
                        const dayEvents = eventsOn(key);
                        return (
                            <div
                                key={key}
                                className={`min-h-16 rounded border p-1 ${inMonth ? '' : 'opacity-40'} ${isHoliday ? 'bg-amber-50 dark:bg-amber-950/30' : ''}`}
                            >
                                <div className="text-muted-foreground text-right text-[10px]">{d.getDate()}</div>
                                {isHoliday && <div className="truncate text-[10px] text-amber-700 dark:text-amber-400">Holiday</div>}
                                {dayEvents.slice(0, 3).map((e) => (
                                    <div
                                        key={e.id}
                                        className={`mt-0.5 truncate rounded px-1 text-[10px] ${e.status === 'pending' ? 'border border-dashed' : 'text-white'}`}
                                        style={e.status === 'approved' ? { backgroundColor: e.color ?? '#2563eb' } : undefined}
                                        title={`${e.employee} — ${e.type} (${e.status})`}
                                    >
                                        {e.employee.split(' ')[0]} · {e.code}
                                    </div>
                                ))}
                                {dayEvents.length > 3 && <div className="text-muted-foreground text-[10px]">+{dayEvents.length - 3}</div>}
                            </div>
                        );
                    })}
                </div>
            </div>
        </Card>
    );
}

export default function LeaveIndex({
    month,
    calendarRange,
    pending,
    calendarLeave,
    holidays,
    canManage,
    canFileOnBehalf,
    hasOwnRecord,
    leaveTypes,
    employees,
}: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">Leave</h1>
                    <div className="flex flex-wrap gap-2">
                        {hasOwnRecord && (
                            <Link
                                href="/hr/me/leave"
                                className="bg-primary text-primary-foreground inline-flex items-center gap-1 rounded-md px-3 text-sm font-medium"
                            >
                                <Plus className="h-4 w-4" /> New application
                            </Link>
                        )}
                        {canFileOnBehalf && <FileLeaveDialog employees={employees} leaveTypes={leaveTypes} />}
                        {canManage && (
                            <>
                                <Link href="/hr/leave/balances" className="inline-flex items-center gap-1 rounded-md border px-3 text-sm">
                                    <ListChecks className="h-4 w-4" /> Balances
                                </Link>
                                <Link href="/hr/leave/types" className="inline-flex items-center gap-1 rounded-md border px-3 text-sm">
                                    <CalendarDays className="h-4 w-4" /> Types
                                </Link>
                                <Link href="/hr/leave/holidays" className="inline-flex items-center gap-1 rounded-md border px-3 text-sm">
                                    Holidays
                                </Link>
                                <Link href="/hr/leave/settings" className="inline-flex items-center gap-1 rounded-md border px-3 text-sm">
                                    <CalendarCog className="h-4 w-4" /> Settings
                                </Link>
                            </>
                        )}
                    </div>
                </div>

                <Card className="p-4">
                    <h2 className="mb-2 text-sm font-semibold">Awaiting your decision ({pending.length})</h2>
                    <div className="grid gap-2">
                        {pending.map((r) => (
                            <div key={r.id} className="flex flex-wrap items-center justify-between gap-3 rounded border p-3 text-sm">
                                <div>
                                    <Link href={`/hr/leave/requests/${r.id}`} className="text-primary font-medium hover:underline">
                                        {r.employee}
                                    </Link>{' '}
                                    — {r.type} · {r.days} day(s)
                                    {r.is_emergency && <Badge className="ml-2">emergency</Badge>}
                                    <div className="text-muted-foreground text-xs">
                                        {fmtDate(r.start_date)} → {fmtDate(r.end_date)}
                                        {r.reason ? ` · ${r.reason}` : ''}
                                    </div>
                                </div>
                                <DecideButtons request={r} />
                            </div>
                        ))}
                        {pending.length === 0 && <p className="text-muted-foreground text-sm">Nothing pending.</p>}
                    </div>
                </Card>

                <MonthCalendar month={month} range={calendarRange} events={calendarLeave} holidays={holidays} />
            </div>
        </AppLayout>
    );
}
