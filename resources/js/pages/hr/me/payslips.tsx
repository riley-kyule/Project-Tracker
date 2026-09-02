import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';

type PayslipRow = {
    id: number;
    period: string;
    pay_date: string;
    gross_pay: number;
    total_deductions: number;
    net_pay: number;
    currency: string;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Payslips', href: '/hr/me/payslips' }];

export default function MyPayslips({ payslips }: { payslips: PayslipRow[] }) {
    const money = (v: number, c: string) => `${c} ${v.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Payslips" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">My Payslips</h1>
                <Card className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Period</th>
                                <th className="px-3 py-2 font-medium">Pay date</th>
                                <th className="px-3 py-2 font-medium">Gross</th>
                                <th className="px-3 py-2 font-medium">Deductions</th>
                                <th className="px-3 py-2 font-medium">Net pay</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {payslips.map((p) => (
                                <tr key={p.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">{p.period}</td>
                                    <td className="px-3 py-2">{p.pay_date}</td>
                                    <td className="px-3 py-2">{money(p.gross_pay, p.currency)}</td>
                                    <td className="px-3 py-2">{money(p.total_deductions, p.currency)}</td>
                                    <td className="px-3 py-2 font-medium">{money(p.net_pay, p.currency)}</td>
                                    <td className="px-3 py-2 text-right">
                                        <a href={`/hr/me/payslips/${p.id}/download`}>
                                            <Button variant="ghost" size="sm">
                                                <Download className="mr-1 h-4 w-4" /> PDF
                                            </Button>
                                        </a>
                                    </td>
                                </tr>
                            ))}
                            {payslips.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-3 py-8 text-center">
                                        No payslips yet.
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
