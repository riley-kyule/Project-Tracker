import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Balance = {
    id: number;
    type: string;
    code: string;
    entitled_days: number;
    carried_over_days: number;
    taken_days: number;
    pending_days: number;
    adjustment_days: number;
    available_days: number;
};

type Row = { id: number; name: string; department: string | null; period_start: string; balances: Balance[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leave', href: '/hr/leave' },
    { title: 'Balances', href: '/hr/leave/balances' },
];

function AdjustDialog({ balance, employeeName }: { balance: Balance; employeeName: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({
        entitled_days: balance.entitled_days,
        adjustment_days: balance.adjustment_days,
        adjustment_reason: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/hr/leave/balances/${balance.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    Adjust
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Adjust {balance.type} — {employeeName}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="entitled">Entitled days</Label>
                        <Input
                            id="entitled"
                            type="number"
                            step="0.5"
                            value={data.entitled_days}
                            onChange={(e) => setData('entitled_days', Number(e.target.value))}
                        />
                        <InputError message={errors.entitled_days} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="adjust">Manual adjustment (±)</Label>
                        <Input
                            id="adjust"
                            type="number"
                            step="0.5"
                            value={data.adjustment_days}
                            onChange={(e) => setData('adjustment_days', Number(e.target.value))}
                        />
                        <InputError message={errors.adjustment_days} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="reason">Reason</Label>
                        <Input id="reason" value={data.adjustment_reason} onChange={(e) => setData('adjustment_reason', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function LeaveBalancesPage({ employees }: { employees: Row[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave balances" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Leave balances</h1>
                {employees.map((emp) => (
                    <div key={emp.id} className="rounded-lg border">
                        <div className="bg-muted/40 flex items-center justify-between border-b px-3 py-2">
                            <div>
                                <span className="font-medium">{emp.name}</span>
                                <span className="text-muted-foreground ml-2 text-xs">
                                    {emp.department ?? '—'} · period from {fmtDate(emp.period_start)}
                                </span>
                            </div>
                            {emp.balances.length === 0 && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => router.post(`/hr/leave/balances/${emp.id}/provision`, {}, { preserveScroll: true })}
                                >
                                    Provision balances
                                </Button>
                            )}
                        </div>
                        {emp.balances.length > 0 && (
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-3 py-1.5 font-medium">Type</th>
                                        <th className="px-3 py-1.5 font-medium">Entitled</th>
                                        <th className="px-3 py-1.5 font-medium">Carried</th>
                                        <th className="px-3 py-1.5 font-medium">Taken</th>
                                        <th className="px-3 py-1.5 font-medium">Pending</th>
                                        <th className="px-3 py-1.5 font-medium">Adj.</th>
                                        <th className="px-3 py-1.5 font-medium">Available</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {emp.balances.map((b) => (
                                        <tr key={b.id} className="border-t">
                                            <td className="px-3 py-1.5">{b.type}</td>
                                            <td className="px-3 py-1.5">{b.entitled_days}</td>
                                            <td className="px-3 py-1.5">{b.carried_over_days}</td>
                                            <td className="px-3 py-1.5">{b.taken_days}</td>
                                            <td className="px-3 py-1.5">{b.pending_days}</td>
                                            <td className="px-3 py-1.5">{b.adjustment_days}</td>
                                            <td className="px-3 py-1.5 font-medium">{b.available_days}</td>
                                            <td className="px-3 py-1.5 text-right">
                                                <AdjustDialog balance={b} employeeName={emp.name} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
