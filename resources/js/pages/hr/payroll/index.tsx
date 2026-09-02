import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Sliders } from 'lucide-react';
import { useState } from 'react';

type PeriodRow = {
    id: number;
    label: string;
    year: number;
    month: number;
    status: string;
    pay_date: string;
    payslips_count: number;
    net_total: number;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Payroll', href: '/hr/payroll' }];

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'outline',
    processing: 'secondary',
    review: 'secondary',
    approved: 'default',
    paid: 'default',
    closed: 'outline',
};

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

function CreatePeriodDialog() {
    const [open, setOpen] = useState(false);
    const now = new Date();
    const { data, setData, post, processing, errors, transform } = useForm({
        year: now.getFullYear(),
        month: now.getMonth() + 1,
        pay_date: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((f) => ({ ...f, pay_date: f.pay_date || null }));
        post('/hr/payroll', { onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="mr-1 h-4 w-4" /> New period
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New payroll period</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="year">Year</Label>
                            <Input id="year" type="number" value={data.year} onChange={(e) => setData('year', Number(e.target.value))} />
                            <InputError message={errors.year} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="month">Month</Label>
                            <Select value={String(data.month)} onValueChange={(v) => setData('month', Number(v))}>
                                <SelectTrigger id="month">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {MONTHS.map((m, i) => (
                                        <SelectItem key={m} value={String(i + 1)}>
                                            {m}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.month} />
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="pay_date">Pay date (optional)</Label>
                        <Input id="pay_date" type="date" value={data.pay_date} onChange={(e) => setData('pay_date', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Create
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PayrollIndex({ periods }: { periods: PeriodRow[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">Payroll</h1>
                    <div className="flex gap-2">
                        <Link href="/hr/payroll/rate-sets" className="inline-flex items-center gap-1 rounded-md border px-3 text-sm">
                            <Sliders className="h-4 w-4" /> Statutory rates
                        </Link>
                        <CreatePeriodDialog />
                    </div>
                </div>

                <Card className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Period</th>
                                <th className="px-3 py-2 font-medium">Pay date</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Payslips</th>
                                <th className="px-3 py-2 font-medium">Net total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {periods.map((p) => (
                                <tr key={p.id} className="hover:bg-muted/30 border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/hr/payroll/${p.id}`} className="text-primary font-medium hover:underline">
                                            {p.label}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{p.pay_date}</td>
                                    <td className="px-3 py-2">
                                        <Badge variant={STATUS_VARIANT[p.status] ?? 'outline'}>{p.status}</Badge>
                                    </td>
                                    <td className="px-3 py-2">{p.payslips_count}</td>
                                    <td className="px-3 py-2">KES {p.net_total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                                </tr>
                            ))}
                            {periods.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground px-3 py-8 text-center">
                                        No payroll periods yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </Card>
            </div>
        </AppLayout>
    );
}
