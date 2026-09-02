import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type LeaveType = {
    id: number;
    name: string;
    code: string;
    is_paid: boolean;
    accrual_method: string;
    default_days: string | number | null;
    gender_eligibility: string | null;
    counts_toward_overlap_block: boolean;
    is_emergency: boolean;
    requires_document: boolean;
    requires_approval: boolean;
    min_notice_days: number | null;
    is_active: boolean;
    color: string | null;
    requests_count: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leave', href: '/hr/leave' },
    { title: 'Types', href: '/hr/leave/types' },
];

const NONE = 'none';
const ACCRUAL_METHODS = ['entitlement', 'monthly_accrual', 'none'];

function label(value: string) {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function TypeDialog({ type }: { type?: LeaveType }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, reset, transform } = useForm({
        name: type?.name ?? '',
        code: type?.code ?? '',
        is_paid: type?.is_paid ?? true,
        accrual_method: type?.accrual_method ?? 'entitlement',
        default_days: type?.default_days != null ? String(type.default_days) : '',
        gender_eligibility: type?.gender_eligibility ?? NONE,
        counts_toward_overlap_block: type?.counts_toward_overlap_block ?? true,
        is_emergency: type?.is_emergency ?? false,
        requires_document: type?.requires_document ?? false,
        requires_approval: type?.requires_approval ?? true,
        min_notice_days: type?.min_notice_days != null ? String(type.min_notice_days) : '',
        is_active: type?.is_active ?? true,
        color: type?.color ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((f) => ({
            ...f,
            default_days: f.default_days === '' ? null : Number(f.default_days),
            min_notice_days: f.min_notice_days === '' ? null : Number(f.min_notice_days),
            gender_eligibility: f.gender_eligibility === NONE ? null : f.gender_eligibility,
            color: f.color || null,
        }));
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                if (!type) reset();
            },
        };
        if (type) patch(`/hr/leave/types/${type.id}`, opts);
        else post('/hr/leave/types', opts);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {type ? (
                    <Button variant="ghost" size="sm">
                        <Pencil className="h-4 w-4" />
                    </Button>
                ) : (
                    <Button>
                        <Plus className="mr-1 h-4 w-4" /> New type
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{type ? 'Edit' : 'New'} leave type</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="lt-name">Name</Label>
                        <Input id="lt-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="lt-code">Code</Label>
                        <Input id="lt-code" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} />
                        <InputError message={errors.code} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="lt-accrual">Accrual method</Label>
                        <Select value={data.accrual_method} onValueChange={(v) => setData('accrual_method', v)}>
                            <SelectTrigger id="lt-accrual">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {ACCRUAL_METHODS.map((m) => (
                                    <SelectItem key={m} value={m}>
                                        {label(m)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="lt-days">Default days (blank = uncapped)</Label>
                        <Input
                            id="lt-days"
                            type="number"
                            step="0.5"
                            value={data.default_days}
                            onChange={(e) => setData('default_days', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="lt-gender">Eligibility</Label>
                        <Select value={data.gender_eligibility} onValueChange={(v) => setData('gender_eligibility', v)}>
                            <SelectTrigger id="lt-gender">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Everyone</SelectItem>
                                <SelectItem value="female">Female only</SelectItem>
                                <SelectItem value="male">Male only</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="lt-notice">Min notice days (blank = use default)</Label>
                        <Input
                            id="lt-notice"
                            type="number"
                            value={data.min_notice_days}
                            onChange={(e) => setData('min_notice_days', e.target.value)}
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.is_paid} onCheckedChange={(v) => setData('is_paid', v === true)} /> Paid
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.requires_approval} onCheckedChange={(v) => setData('requires_approval', v === true)} /> Requires
                        approval
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.counts_toward_overlap_block}
                            onCheckedChange={(v) => setData('counts_toward_overlap_block', v === true)}
                        />{' '}
                        Counts toward the same-department block
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.is_emergency} onCheckedChange={(v) => setData('is_emergency', v === true)} /> Emergency (bypasses
                        block &amp; notice)
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.requires_document} onCheckedChange={(v) => setData('requires_document', v === true)} /> Requires a
                        document
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} /> Active
                    </label>
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function LeaveTypesPage({ leaveTypes }: { leaveTypes: LeaveType[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave types" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Leave types</h1>
                    <TypeDialog />
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Code</th>
                                <th className="px-3 py-2 font-medium">Default days</th>
                                <th className="px-3 py-2 font-medium">Accrual</th>
                                <th className="px-3 py-2 font-medium">Flags</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {leaveTypes.map((t) => (
                                <tr key={t.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">
                                        {t.name} {!t.is_active && <Badge variant="outline">inactive</Badge>}
                                    </td>
                                    <td className="text-muted-foreground px-3 py-2">{t.code}</td>
                                    <td className="px-3 py-2">{t.default_days ?? '—'}</td>
                                    <td className="px-3 py-2">{label(t.accrual_method)}</td>
                                    <td className="text-muted-foreground px-3 py-2 text-xs">
                                        {[
                                            !t.is_paid && 'unpaid',
                                            t.is_emergency && 'emergency',
                                            !t.counts_toward_overlap_block && 'overlap-exempt',
                                            t.gender_eligibility,
                                            t.requires_document && 'doc required',
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <TypeDialog type={t} />
                                        {t.requests_count === 0 && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    if (confirm('Delete this leave type?'))
                                                        router.delete(`/hr/leave/types/${t.id}`, { preserveScroll: true });
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
