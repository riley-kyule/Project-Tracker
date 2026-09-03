import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useRef } from 'react';

type Settings = {
    company_kra_pin: string | null;
    nssf_employer_number: string | null;
    shif_employer_number: string | null;
    payroll_currency: string;
    default_pay_day: number;
    nita_levy_enabled: boolean;
    payslip_company_name: string | null;
    payslip_company_address: string | null;
    payslip_footer_note: string | null;
    payslip_dispatch_timing: string;
    payroll_requires_second_approval: boolean;
    has_logo: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/hr/payroll' },
    { title: 'Settings', href: '/hr/payroll/settings' },
];

export default function PayrollSettings({ settings }: { settings: Settings }) {
    const logoRef = useRef<HTMLInputElement>(null);
    const { data, setData, patch, processing, errors } = useForm({
        company_kra_pin: settings.company_kra_pin ?? '',
        nssf_employer_number: settings.nssf_employer_number ?? '',
        shif_employer_number: settings.shif_employer_number ?? '',
        payroll_currency: settings.payroll_currency ?? 'KES',
        default_pay_day: settings.default_pay_day ?? 28,
        nita_levy_enabled: settings.nita_levy_enabled ?? true,
        payslip_company_name: settings.payslip_company_name ?? '',
        payslip_company_address: settings.payslip_company_address ?? '',
        payslip_footer_note: settings.payslip_footer_note ?? '',
        payslip_dispatch_timing: settings.payslip_dispatch_timing ?? 'on_mark_paid',
        payroll_requires_second_approval: settings.payroll_requires_second_approval ?? false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch('/hr/payroll/settings', { preserveScroll: true });
    };

    const uploadLogo = (file: File) => {
        router.post('/hr/payroll/settings/logo', { logo: file }, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll settings" />
            <form onSubmit={submit} className="flex max-w-3xl flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Payroll settings</h1>

                <Card className="grid gap-4 p-4 sm:grid-cols-2">
                    <h2 className="text-sm font-semibold sm:col-span-2">Employer identifiers</h2>
                    <div className="grid gap-1.5">
                        <Label htmlFor="kra">Company KRA PIN</Label>
                        <Input id="kra" value={data.company_kra_pin} onChange={(e) => setData('company_kra_pin', e.target.value)} />
                        <InputError message={errors.company_kra_pin} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="nssf">NSSF employer number</Label>
                        <Input id="nssf" value={data.nssf_employer_number} onChange={(e) => setData('nssf_employer_number', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="shif">SHA/SHIF employer number</Label>
                        <Input id="shif" value={data.shif_employer_number} onChange={(e) => setData('shif_employer_number', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="cur">Currency</Label>
                        <Input
                            id="cur"
                            maxLength={3}
                            value={data.payroll_currency}
                            onChange={(e) => setData('payroll_currency', e.target.value.toUpperCase())}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="payday">Default pay day (day of month)</Label>
                        <Input
                            id="payday"
                            type="number"
                            min={1}
                            max={31}
                            value={data.default_pay_day}
                            onChange={(e) => setData('default_pay_day', Number(e.target.value))}
                        />
                        <InputError message={errors.default_pay_day} />
                    </div>
                    <label className="flex items-center gap-2 text-sm sm:col-span-2">
                        <Checkbox checked={data.nita_levy_enabled} onCheckedChange={(v) => setData('nita_levy_enabled', v === true)} />
                        Apply the NITA levy (employer, KES 50 / employee / month)
                    </label>
                </Card>

                <Card className="grid gap-4 p-4">
                    <h2 className="text-sm font-semibold">Payslip letterhead</h2>
                    <div className="flex items-start gap-4">
                        <div className="flex flex-col items-center gap-2">
                            {settings.has_logo ? (
                                <img src="/hr/payroll/settings/logo" alt="Logo" className="h-16 w-16 rounded border object-contain" />
                            ) : (
                                <div className="text-muted-foreground flex h-16 w-16 items-center justify-center rounded border text-xs">No logo</div>
                            )}
                            <div className="flex gap-1">
                                <input
                                    ref={logoRef}
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="hidden"
                                    onChange={(e) => e.target.files?.[0] && uploadLogo(e.target.files[0])}
                                />
                                <Button type="button" size="sm" variant="outline" onClick={() => logoRef.current?.click()}>
                                    {settings.has_logo ? 'Replace' : 'Upload'}
                                </Button>
                                {settings.has_logo && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => router.delete('/hr/payroll/settings/logo', { preserveScroll: true })}
                                    >
                                        Remove
                                    </Button>
                                )}
                            </div>
                        </div>
                        <div className="grid flex-1 gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="cname">Company name on the payslip</Label>
                                <Input
                                    id="cname"
                                    placeholder="Defaults to the app name"
                                    value={data.payslip_company_name}
                                    onChange={(e) => setData('payslip_company_name', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="caddr">Company address</Label>
                                <textarea
                                    id="caddr"
                                    rows={3}
                                    className="border-input bg-background rounded-md border p-2 text-sm"
                                    value={data.payslip_company_address}
                                    onChange={(e) => setData('payslip_company_address', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="foot">Footer note</Label>
                                <Input id="foot" value={data.payslip_footer_note} onChange={(e) => setData('payslip_footer_note', e.target.value)} />
                            </div>
                        </div>
                    </div>
                </Card>

                <Card className="grid gap-4 p-4">
                    <h2 className="text-sm font-semibold">Sending &amp; approval</h2>
                    <div className="grid gap-1.5">
                        <Label>When are payslip emails sent?</Label>
                        <Select value={data.payslip_dispatch_timing} onValueChange={(v) => setData('payslip_dispatch_timing', v)}>
                            <SelectTrigger className="max-w-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="on_mark_paid">Immediately when the run is marked paid</SelectItem>
                                <SelectItem value="on_pay_date">On the pay date</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.payroll_requires_second_approval}
                            onCheckedChange={(v) => setData('payroll_requires_second_approval', v === true)}
                        />
                        Require a second sign-off (CEO / Administrator) before payslips can be sent
                    </label>
                    <p className="text-muted-foreground text-xs">
                        Off: the HR Manager processes and dispatches payroll on their own. On: the HR Manager processes, then a CEO/Admin approves and
                        dispatches.
                    </p>
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
