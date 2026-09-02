import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Balance = {
    type: string;
    code: string;
    entitled_days: number;
    carried_over_days: number;
    taken_days: number;
    pending_days: number;
    available_days: number;
};

type MyRequest = {
    id: number;
    type: string;
    status: string;
    days: number;
    start_date: string;
    end_date: string;
    is_emergency: boolean;
    decision_note: string | null;
    can_cancel: boolean;
};

type LeaveTypeOption = {
    id: number;
    name: string;
    code: string;
    is_emergency: boolean;
    requires_document: boolean;
    gender_eligibility: string | null;
    requires_approval: boolean;
};

type PageProps = {
    employee: { id: number; name: string; gender: string | null; department: string | null; period: { start: string; end: string | null } };
    balances: Balance[];
    requests: MyRequest[];
    leaveTypes: LeaveTypeOption[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Leave', href: '/hr/me/leave' }];

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    approved: 'default',
    pending: 'secondary',
    rejected: 'destructive',
    cancelled: 'outline',
    withdrawn: 'outline',
};

export default function MyLeave({ employee, balances, requests, leaveTypes }: PageProps) {
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        leave_type_id: '',
        start_date: '',
        end_date: '',
        reason: '',
        contact_during_leave: '',
        is_emergency: false as boolean,
    });

    const [blocked, setBlocked] = useState<string[]>([]);
    const [holidays, setHolidays] = useState<string[]>([]);

    const eligibleTypes = leaveTypes.filter(
        (t) => !t.gender_eligibility || (employee.gender && t.gender_eligibility.toLowerCase() === employee.gender.toLowerCase()),
    );

    useEffect(() => {
        if (!data.start_date || !data.end_date) return;
        const params = new URLSearchParams({ from: data.start_date, to: data.end_date });
        fetch(`/hr/leave/calendar?${params.toString()}`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : { blocked: [], holidays: [] }))
            .then((d) => {
                setBlocked(d.blocked ?? []);
                setHolidays(d.holidays ?? []);
            })
            .catch(() => undefined);
    }, [data.start_date, data.end_date]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((f) => ({ ...f, leave_type_id: Number(f.leave_type_id) }));
        post('/hr/leave/requests', { preserveScroll: true, onSuccess: () => reset() });
    };

    const showBlockedWarning = blocked.length > 0 && !data.is_emergency;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Leave" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">My Leave</h1>
                    <p className="text-muted-foreground text-sm">
                        {employee.department ?? '—'} · entitlement period from {employee.period.start}
                        {employee.period.end ? ` to ${employee.period.end}` : ''}
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {balances.map((b) => (
                        <Card key={b.code} className="p-3">
                            <div className="text-muted-foreground text-xs">{b.type}</div>
                            <div className="text-2xl font-semibold">{b.available_days}</div>
                            <div className="text-muted-foreground text-xs">
                                {b.taken_days} taken · {b.pending_days} pending · of {b.entitled_days + b.carried_over_days}
                            </div>
                        </Card>
                    ))}
                    {balances.length === 0 && <p className="text-muted-foreground text-sm">No tracked balances yet.</p>}
                </div>

                <Card className="grid max-w-2xl gap-4 p-4">
                    <h2 className="text-sm font-semibold">Apply for leave</h2>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="type">Leave type</Label>
                            <Select value={data.leave_type_id} onValueChange={(v) => setData('leave_type_id', v)}>
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {eligibleTypes.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.leave_type_id} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="start">Start date</Label>
                                <Input id="start" type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} />
                                <InputError message={errors.start_date} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="end">End date</Label>
                                <Input id="end" type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} />
                                <InputError message={errors.end_date} />
                            </div>
                        </div>

                        {holidays.length > 0 && (
                            <p className="text-muted-foreground text-xs">Public holiday(s) in range (not counted): {holidays.join(', ')}</p>
                        )}
                        {showBlockedWarning && (
                            <div className="rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                                A colleague in your department is already on leave on: {blocked.join(', ')}. Pick other dates, tick “emergency leave”,
                                or ask HR to override.
                            </div>
                        )}

                        <div className="grid gap-1.5">
                            <Label htmlFor="reason">Reason</Label>
                            <Input id="reason" value={data.reason} onChange={(e) => setData('reason', e.target.value)} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="contact">Contact while away</Label>
                            <Input id="contact" value={data.contact_during_leave} onChange={(e) => setData('contact_during_leave', e.target.value)} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.is_emergency} onCheckedChange={(v) => setData('is_emergency', v === true)} />
                            Emergency leave
                        </label>
                        <div>
                            <Button type="submit" disabled={processing}>
                                Submit request
                            </Button>
                        </div>
                    </form>
                </Card>

                <Card className="p-4">
                    <h2 className="mb-2 text-sm font-semibold">My requests</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-muted-foreground text-left">
                                <tr>
                                    <th className="py-1 pr-3">Type</th>
                                    <th className="py-1 pr-3">Dates</th>
                                    <th className="py-1 pr-3">Days</th>
                                    <th className="py-1 pr-3">Status</th>
                                    <th className="py-1 pr-3">Note</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {requests.map((r) => (
                                    <tr key={r.id} className="border-t">
                                        <td className="py-1.5 pr-3">
                                            {r.type} {r.is_emergency && <Badge variant="secondary">emergency</Badge>}
                                        </td>
                                        <td className="py-1.5 pr-3">
                                            {r.start_date} → {r.end_date}
                                        </td>
                                        <td className="py-1.5 pr-3">{r.days}</td>
                                        <td className="py-1.5 pr-3">
                                            <Badge variant={STATUS_VARIANT[r.status] ?? 'outline'}>{r.status}</Badge>
                                        </td>
                                        <td className="text-muted-foreground py-1.5 pr-3">{r.decision_note ?? '—'}</td>
                                        <td className="py-1.5">
                                            {r.can_cancel && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        if (confirm('Cancel this request?'))
                                                            router.post(`/hr/leave/requests/${r.id}/cancel`, {}, { preserveScroll: true });
                                                    }}
                                                >
                                                    Cancel
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {requests.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground py-4 text-center">
                                            No requests yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
