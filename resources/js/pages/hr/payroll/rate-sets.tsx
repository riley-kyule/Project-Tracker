import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Band = { upto: number | null; rate: number };

type Payload = {
    currency?: string;
    paye_bands: Band[];
    personal_relief_monthly: number;
    insurance_relief: { rate: number; cap_monthly: number | null };
    nssf: { tier1_upper: number; tier2_upper: number; rate: number; employer_matches: boolean };
    shif: { rate: number; min_monthly: number | null; cap_monthly: number | null };
    housing_levy: { employee_rate: number; employer_rate: number };
    nita_levy_monthly: number | null;
    deductible_from_taxable?: Record<string, boolean>;
};

type RateSet = {
    id: number;
    name: string;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
    payload: Payload;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/hr/payroll' },
    { title: 'Statutory rates', href: '/hr/payroll/rate-sets' },
];

const BLANK: Payload = {
    currency: 'KES',
    paye_bands: [
        { upto: 24000, rate: 0.1 },
        { upto: 32333, rate: 0.25 },
        { upto: 500000, rate: 0.3 },
        { upto: 800000, rate: 0.325 },
        { upto: null, rate: 0.35 },
    ],
    personal_relief_monthly: 2400,
    insurance_relief: { rate: 0.15, cap_monthly: 5000 },
    nssf: { tier1_upper: 9000, tier2_upper: 108000, rate: 0.06, employer_matches: true },
    shif: { rate: 0.0275, min_monthly: 300, cap_monthly: null },
    housing_levy: { employee_rate: 0.015, employer_rate: 0.015 },
    nita_levy_monthly: 50,
    deductible_from_taxable: { nssf: true, shif: true, housing_levy: true },
};

function RateSetDialog({ rateSet }: { rateSet?: RateSet }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors } = useForm<{
        name: string;
        effective_from: string;
        effective_to: string;
        is_active: boolean;
        payload: Payload;
    }>({
        name: rateSet?.name ?? '',
        effective_from: rateSet?.effective_from ?? '',
        effective_to: rateSet?.effective_to ?? '',
        is_active: rateSet?.is_active ?? true,
        payload: rateSet ? JSON.parse(JSON.stringify(rateSet.payload)) : JSON.parse(JSON.stringify(BLANK)),
    });

    const p = data.payload;
    const setP = (patchObj: Partial<Payload>) => setData('payload', { ...p, ...patchObj });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { onSuccess: () => setOpen(false) };
        if (rateSet) patch(`/hr/payroll/rate-sets/${rateSet.id}`, opts);
        else post('/hr/payroll/rate-sets', opts);
    };

    const num = (label: string, value: number | null, onChange: (v: number) => void, step = 'any') => (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <Input type="number" step={step} value={value ?? ''} onChange={(e) => onChange(Number(e.target.value))} />
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {rateSet ? (
                    <Button variant="ghost" size="sm">
                        <Pencil className="h-4 w-4" />
                    </Button>
                ) : (
                    <Button>
                        <Plus className="mr-1 h-4 w-4" /> New rate set
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{rateSet ? 'Edit' : 'New'} statutory rate set</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid grid-cols-3 gap-3">
                        <div className="grid gap-1.5">
                            <Label>Name</Label>
                            <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Effective from</Label>
                            <Input type="date" value={data.effective_from} onChange={(e) => setData('effective_from', e.target.value)} />
                            <InputError message={errors.effective_from} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Effective to</Label>
                            <Input type="date" value={data.effective_to} onChange={(e) => setData('effective_to', e.target.value)} />
                        </div>
                    </div>

                    <div>
                        <h3 className="mb-1 text-sm font-semibold">PAYE bands</h3>
                        {p.paye_bands.map((b, i) => (
                            <div key={i} className="mb-1 flex items-center gap-2">
                                <Input
                                    type="number"
                                    placeholder="upper limit (blank = top)"
                                    value={b.upto ?? ''}
                                    onChange={(e) => {
                                        const bands = [...p.paye_bands];
                                        bands[i] = { ...b, upto: e.target.value === '' ? null : Number(e.target.value) };
                                        setP({ paye_bands: bands });
                                    }}
                                />
                                <Input
                                    type="number"
                                    step="0.001"
                                    placeholder="rate (0–1)"
                                    value={b.rate}
                                    onChange={(e) => {
                                        const bands = [...p.paye_bands];
                                        bands[i] = { ...b, rate: Number(e.target.value) };
                                        setP({ paye_bands: bands });
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setP({ paye_bands: p.paye_bands.filter((_, j) => j !== i) })}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setP({ paye_bands: [...p.paye_bands, { upto: null, rate: 0 }] })}
                        >
                            Add band
                        </Button>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        {num('Personal relief / month', p.personal_relief_monthly, (v) => setP({ personal_relief_monthly: v }))}
                        {num('Insurance relief rate', p.insurance_relief.rate, (v) => setP({ insurance_relief: { ...p.insurance_relief, rate: v } }))}
                        {num('Insurance relief cap', p.insurance_relief.cap_monthly, (v) =>
                            setP({ insurance_relief: { ...p.insurance_relief, cap_monthly: v } }),
                        )}
                        {num('NSSF tier 1 upper', p.nssf.tier1_upper, (v) => setP({ nssf: { ...p.nssf, tier1_upper: v } }))}
                        {num('NSSF tier 2 upper', p.nssf.tier2_upper, (v) => setP({ nssf: { ...p.nssf, tier2_upper: v } }))}
                        {num('NSSF rate', p.nssf.rate, (v) => setP({ nssf: { ...p.nssf, rate: v } }))}
                        {num('SHIF rate', p.shif.rate, (v) => setP({ shif: { ...p.shif, rate: v } }))}
                        {num('SHIF minimum', p.shif.min_monthly, (v) => setP({ shif: { ...p.shif, min_monthly: v } }))}
                        {num('SHIF cap (blank = none)', p.shif.cap_monthly, (v) => setP({ shif: { ...p.shif, cap_monthly: v } }))}
                        {num('AHL employee rate', p.housing_levy.employee_rate, (v) =>
                            setP({ housing_levy: { ...p.housing_levy, employee_rate: v } }),
                        )}
                        {num('AHL employer rate', p.housing_levy.employer_rate, (v) =>
                            setP({ housing_levy: { ...p.housing_levy, employer_rate: v } }),
                        )}
                        {num('NITA levy / month', p.nita_levy_monthly, (v) => setP({ nita_levy_monthly: v }))}
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={p.nssf.employer_matches}
                            onCheckedChange={(v) => setP({ nssf: { ...p.nssf, employer_matches: v === true } })}
                        />
                        Employer matches NSSF
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.is_active} onCheckedChange={(v) => setData('is_active', v === true)} />
                        Active
                    </label>
                    <InputError message={errors['payload' as keyof typeof errors]} />
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function RateSetsPage({ rateSets }: { rateSets: RateSet[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Statutory rates" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Statutory rate sets</h1>
                        <p className="text-muted-foreground text-sm">
                            The rates each payroll run applies. Reconcile a sample run against the Aren / KRA calculator before going live.
                        </p>
                    </div>
                    <RateSetDialog />
                </div>
                <div className="grid gap-3">
                    {rateSets.map((rs) => (
                        <Card key={rs.id} className="flex items-center justify-between p-4">
                            <div>
                                <div className="font-medium">
                                    {rs.name} {!rs.is_active && <Badge variant="outline">inactive</Badge>}
                                </div>
                                <div className="text-muted-foreground text-xs">
                                    From {rs.effective_from}
                                    {rs.effective_to ? ` to ${rs.effective_to}` : ' (open)'} · personal relief {rs.payload.personal_relief_monthly} ·
                                    NSSF {(rs.payload.nssf.rate * 100).toFixed(1)}% · SHIF {(rs.payload.shif.rate * 100).toFixed(2)}% · AHL{' '}
                                    {(rs.payload.housing_levy.employee_rate * 100).toFixed(1)}%
                                </div>
                            </div>
                            <RateSetDialog rateSet={rs} />
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
