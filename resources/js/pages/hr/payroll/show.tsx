import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type Payslip = {
    id: number;
    employee: string;
    staff_number: string;
    gross_pay: number;
    paye: number;
    nssf_employee: number;
    shif_employee: number;
    housing_levy_employee: number;
    total_deductions: number;
    net_pay: number;
    employer_cost: number;
    has_pdf: boolean;
};

type Period = {
    id: number;
    label: string;
    status: string;
    start_date: string;
    end_date: string;
    pay_date: string;
    notes: string | null;
    processed_at: string | null;
    approved_at: string | null;
    paid_at: string | null;
    rate_set: { id: number; name: string } | null;
};

type PageProps = {
    period: Period;
    payslips: Payslip[];
    totals: { gross: number; paye: number; net: number; employer_cost: number };
    reports: string[];
    can: { process: boolean; approve: boolean; markPaid: boolean };
};

const money = (v: number) => `KES ${v.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

function reportLabel(r: string) {
    return r.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function PayrollShow({ period, payslips, totals, reports, can }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Payroll', href: '/hr/payroll' },
        { title: period.label, href: `/hr/payroll/${period.id}` },
    ];

    const act = (verb: string, confirmMsg?: string) => {
        if (confirmMsg && !confirm(confirmMsg)) return;
        router.post(`/hr/payroll/${period.id}/${verb}`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Payroll — ${period.label}`} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{period.label}</h1>
                        <p className="text-muted-foreground text-sm">
                            {period.start_date} – {period.end_date} · pay date {period.pay_date}
                            {period.rate_set ? ` · rates: ${period.rate_set.name}` : ''}
                        </p>
                        <Badge className="mt-1">{period.status}</Badge>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.process && (
                            <Button
                                onClick={() =>
                                    act('process', period.status === 'review' ? 'Re-run this payroll? Existing payslips are replaced.' : undefined)
                                }
                            >
                                {period.status === 'review' ? 'Re-run' : 'Process'}
                            </Button>
                        )}
                        {can.approve && <Button onClick={() => act('approve')}>Approve</Button>}
                        {can.markPaid && <Button onClick={() => act('mark-paid', 'Mark as paid and send payslip notifications?')}>Mark paid</Button>}
                    </div>
                </div>

                {payslips.length > 0 && (
                    <div className="grid gap-3 sm:grid-cols-4">
                        <Card className="p-3">
                            <div className="text-muted-foreground text-xs">Gross</div>
                            <div className="text-lg font-semibold">{money(totals.gross)}</div>
                        </Card>
                        <Card className="p-3">
                            <div className="text-muted-foreground text-xs">PAYE</div>
                            <div className="text-lg font-semibold">{money(totals.paye)}</div>
                        </Card>
                        <Card className="p-3">
                            <div className="text-muted-foreground text-xs">Net pay</div>
                            <div className="text-lg font-semibold">{money(totals.net)}</div>
                        </Card>
                        <Card className="p-3">
                            <div className="text-muted-foreground text-xs">Employer cost</div>
                            <div className="text-lg font-semibold">{money(totals.employer_cost)}</div>
                        </Card>
                    </div>
                )}

                {payslips.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {reports.map((r) => (
                            <a key={r} href={`/hr/payroll/${period.id}/export/${r}`}>
                                <Button variant="outline" size="sm">
                                    {reportLabel(r)} CSV
                                </Button>
                            </a>
                        ))}
                    </div>
                )}

                <Card className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Employee</th>
                                <th className="px-3 py-2 font-medium">Gross</th>
                                <th className="px-3 py-2 font-medium">PAYE</th>
                                <th className="px-3 py-2 font-medium">NSSF</th>
                                <th className="px-3 py-2 font-medium">SHIF</th>
                                <th className="px-3 py-2 font-medium">AHL</th>
                                <th className="px-3 py-2 font-medium">Net pay</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {payslips.map((p) => (
                                <tr key={p.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/hr/payslips/${p.id}`} className="text-primary hover:underline">
                                            {p.employee}
                                        </Link>
                                        <div className="text-muted-foreground text-xs">{p.staff_number}</div>
                                    </td>
                                    <td className="px-3 py-2">{money(p.gross_pay)}</td>
                                    <td className="px-3 py-2">{money(p.paye)}</td>
                                    <td className="px-3 py-2">{money(p.nssf_employee)}</td>
                                    <td className="px-3 py-2">{money(p.shif_employee)}</td>
                                    <td className="px-3 py-2">{money(p.housing_levy_employee)}</td>
                                    <td className="px-3 py-2 font-medium">{money(p.net_pay)}</td>
                                    <td className="px-3 py-2 text-right">
                                        <a href={`/hr/payslips/${p.id}/download`}>
                                            <Button variant="ghost" size="sm">
                                                PDF
                                            </Button>
                                        </a>
                                    </td>
                                </tr>
                            ))}
                            {payslips.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="text-muted-foreground px-3 py-8 text-center">
                                        No payslips yet — process this period to generate them.
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
