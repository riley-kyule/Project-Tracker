import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';

type Line = { name: string; amount: number };

type Payslip = {
    id: number;
    currency: string;
    employee: string;
    staff_number: string;
    job_title: string | null;
    period: string;
    basic_salary: number;
    earnings: (Line & { taxable: boolean })[] | null;
    gross_pay: number;
    pretax_deductions: Line[] | null;
    taxable_pay: number;
    paye_before_relief: number;
    personal_relief: number;
    insurance_relief: number;
    paye: number;
    nssf_employee: number;
    nssf_employer: number;
    shif_employee: number;
    housing_levy_employee: number;
    housing_levy_employer: number;
    nita_employer: number;
    other_deductions: Line[] | null;
    total_deductions: number;
    net_pay: number;
    employer_cost: number;
    ytd: Record<string, number> | null;
};

export default function PayslipShow({ payslip }: { payslip: Payslip }) {
    const money = (v: number) => `${payslip.currency} ${v.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Payroll', href: '/hr/payroll' },
        { title: `${payslip.employee} — ${payslip.period}`, href: `/hr/payslips/${payslip.id}` },
    ];

    const Row = ({ label, value, muted }: { label: string; value: string; muted?: boolean }) => (
        <div className={`flex justify-between py-1 text-sm ${muted ? 'text-muted-foreground' : ''}`}>
            <span>{label}</span>
            <span>{value}</span>
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Payslip — ${payslip.employee}`} />
            <div className="flex max-w-2xl flex-col gap-4 p-4">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">{payslip.employee}</h1>
                        <p className="text-muted-foreground text-sm">
                            {payslip.job_title} · {payslip.staff_number} · {payslip.period}
                        </p>
                    </div>
                    <a href={`/hr/payslips/${payslip.id}/download`}>
                        <Button variant="outline" size="sm">
                            <Download className="mr-1 h-4 w-4" /> PDF
                        </Button>
                    </a>
                </div>

                <Card className="p-4">
                    <h2 className="mb-1 text-sm font-semibold">Earnings</h2>
                    {(payslip.earnings ?? []).map((l, i) => (
                        <Row key={i} label={l.name} value={money(l.amount)} />
                    ))}
                    <div className="mt-1 border-t pt-1">
                        <Row label="Gross pay" value={money(payslip.gross_pay)} />
                    </div>
                </Card>

                <Card className="p-4">
                    <h2 className="mb-1 text-sm font-semibold">Deductions</h2>
                    <Row label="PAYE (after relief)" value={money(payslip.paye)} />
                    <Row label="— tax before relief" value={money(payslip.paye_before_relief)} muted />
                    <Row label="— personal relief" value={`(${money(payslip.personal_relief)})`} muted />
                    {payslip.insurance_relief > 0 && <Row label="— insurance relief" value={`(${money(payslip.insurance_relief)})`} muted />}
                    <Row label="NSSF" value={money(payslip.nssf_employee)} />
                    <Row label="SHIF" value={money(payslip.shif_employee)} />
                    <Row label="Affordable Housing Levy" value={money(payslip.housing_levy_employee)} />
                    {(payslip.pretax_deductions ?? []).map((l, i) => (
                        <Row key={`p${i}`} label={l.name} value={money(l.amount)} />
                    ))}
                    {(payslip.other_deductions ?? []).map((l, i) => (
                        <Row key={`o${i}`} label={l.name} value={money(l.amount)} />
                    ))}
                    <div className="mt-1 border-t pt-1">
                        <Row label="Total deductions" value={money(payslip.total_deductions)} />
                    </div>
                    <div className="mt-1 text-lg font-semibold">Net pay: {money(payslip.net_pay)}</div>
                </Card>

                <Card className="p-4">
                    <h2 className="mb-1 text-sm font-semibold">Employer contributions</h2>
                    <Row label="NSSF (employer)" value={money(payslip.nssf_employer)} />
                    <Row label="Housing Levy (employer)" value={money(payslip.housing_levy_employer)} />
                    {payslip.nita_employer > 0 && <Row label="NITA levy" value={money(payslip.nita_employer)} />}
                    <div className="mt-1 border-t pt-1">
                        <Row label="Total employer cost" value={money(payslip.employer_cost)} />
                    </div>
                </Card>

                {payslip.ytd && (
                    <Card className="p-4">
                        <h2 className="mb-1 text-sm font-semibold">Year to date</h2>
                        {Object.entries(payslip.ytd).map(([k, v]) => (
                            <Row key={k} label={k.replace(/_/g, ' ')} value={money(v)} />
                        ))}
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
