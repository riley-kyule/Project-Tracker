import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type Settings = {
    entitlement_basis: string;
    leave_year_start_month: number;
    default_annual_days: number;
    accrual_enabled: boolean;
    accrual_days_per_month: string | number;
    carryover_enabled: boolean;
    max_carryover_days: number;
    block_same_department_overlap: boolean;
    overlap_exempt_leave_type_codes: string[] | null;
    overlap_override_roles: string[] | null;
    min_notice_days: number;
    require_handover: boolean;
};

type PageProps = {
    settings: Settings;
    leaveTypeCodes: { code: string; name: string }[];
    roles: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leave', href: '/hr/leave' },
    { title: 'Settings', href: '/hr/leave/settings' },
];

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

export default function LeaveSettings({ settings, leaveTypeCodes, roles }: PageProps) {
    const { data, setData, patch, processing, errors } = useForm({
        entitlement_basis: settings.entitlement_basis,
        leave_year_start_month: settings.leave_year_start_month,
        default_annual_days: settings.default_annual_days,
        accrual_enabled: settings.accrual_enabled,
        accrual_days_per_month: Number(settings.accrual_days_per_month),
        carryover_enabled: settings.carryover_enabled,
        max_carryover_days: settings.max_carryover_days,
        block_same_department_overlap: settings.block_same_department_overlap,
        overlap_exempt_leave_type_codes: settings.overlap_exempt_leave_type_codes ?? [],
        overlap_override_roles: settings.overlap_override_roles ?? [],
        min_notice_days: settings.min_notice_days,
        require_handover: settings.require_handover,
    });

    const toggleIn = (key: 'overlap_exempt_leave_type_codes' | 'overlap_override_roles', value: string) => {
        const list = data[key];
        setData(key, list.includes(value) ? list.filter((v) => v !== value) : [...list, value]);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch('/hr/leave/settings', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave settings" />
            <form onSubmit={submit} className="flex max-w-3xl flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Leave settings</h1>

                <Card className="grid gap-4 p-4">
                    <h2 className="text-sm font-semibold">Entitlement</h2>
                    <div className="grid gap-1.5">
                        <Label>Entitlement basis</Label>
                        <Select value={data.entitlement_basis} onValueChange={(v) => setData('entitlement_basis', v)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="contract_period">Per contract period (reset on renewal)</SelectItem>
                                <SelectItem value="calendar_year">Per calendar year</SelectItem>
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-xs">
                            Contract-period basis is what lets “Contract renewed” reset leave to the defaults below.
                        </p>
                    </div>
                    {data.entitlement_basis === 'calendar_year' && (
                        <div className="grid gap-1.5">
                            <Label>Leave year starts</Label>
                            <Select value={String(data.leave_year_start_month)} onValueChange={(v) => setData('leave_year_start_month', Number(v))}>
                                <SelectTrigger>
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
                        </div>
                    )}
                    <div className="grid gap-1.5">
                        <Label htmlFor="default_annual_days">Default annual leave days</Label>
                        <Input
                            id="default_annual_days"
                            type="number"
                            value={data.default_annual_days}
                            onChange={(e) => setData('default_annual_days', Number(e.target.value))}
                            className="max-w-[8rem]"
                        />
                        <InputError message={errors.default_annual_days} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.accrual_enabled} onCheckedChange={(v) => setData('accrual_enabled', v === true)} />
                        Accrue leave monthly instead of granting the full allowance up front
                    </label>
                    {data.accrual_enabled && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="accrual_days_per_month">Accrual days / month</Label>
                            <Input
                                id="accrual_days_per_month"
                                type="number"
                                step="0.01"
                                value={data.accrual_days_per_month}
                                onChange={(e) => setData('accrual_days_per_month', Number(e.target.value))}
                                className="max-w-[8rem]"
                            />
                        </div>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.carryover_enabled} onCheckedChange={(v) => setData('carryover_enabled', v === true)} />
                        Carry unused annual leave into the next period
                    </label>
                    {data.carryover_enabled && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="max_carryover_days">Max carry-over days</Label>
                            <Input
                                id="max_carryover_days"
                                type="number"
                                value={data.max_carryover_days}
                                onChange={(e) => setData('max_carryover_days', Number(e.target.value))}
                                className="max-w-[8rem]"
                            />
                        </div>
                    )}
                </Card>

                <Card className="grid gap-4 p-4">
                    <h2 className="text-sm font-semibold">Same-department overlap</h2>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.block_same_department_overlap}
                            onCheckedChange={(v) => setData('block_same_department_overlap', v === true)}
                        />
                        Block two people in the same department from being on leave at the same time
                    </label>
                    <div className="grid gap-1.5">
                        <Label>Leave types exempt from the block</Label>
                        <div className="flex flex-wrap gap-2">
                            {leaveTypeCodes.map((t) => (
                                <label key={t.code} className="flex items-center gap-1.5 rounded border px-2 py-1 text-xs">
                                    <Checkbox
                                        checked={data.overlap_exempt_leave_type_codes.includes(t.code)}
                                        onCheckedChange={() => toggleIn('overlap_exempt_leave_type_codes', t.code)}
                                    />
                                    {t.name}
                                </label>
                            ))}
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Roles allowed to override the block</Label>
                        <div className="flex flex-wrap gap-2">
                            {roles.map((r) => (
                                <label key={r} className="flex items-center gap-1.5 rounded border px-2 py-1 text-xs">
                                    <Checkbox
                                        checked={data.overlap_override_roles.includes(r)}
                                        onCheckedChange={() => toggleIn('overlap_override_roles', r)}
                                    />
                                    {r}
                                </label>
                            ))}
                        </div>
                    </div>
                </Card>

                <Card className="grid gap-4 p-4">
                    <h2 className="text-sm font-semibold">Requests</h2>
                    <div className="grid gap-1.5">
                        <Label htmlFor="min_notice_days">Minimum notice (days)</Label>
                        <Input
                            id="min_notice_days"
                            type="number"
                            value={data.min_notice_days}
                            onChange={(e) => setData('min_notice_days', Number(e.target.value))}
                            className="max-w-[8rem]"
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.require_handover} onCheckedChange={(v) => setData('require_handover', v === true)} />
                        Require a handover contact on every request
                    </label>
                </Card>

                <div>
                    <Button type="submit" disabled={processing}>
                        Save settings
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
