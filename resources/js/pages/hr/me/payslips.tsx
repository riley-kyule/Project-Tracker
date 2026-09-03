import { SortableHeader, useClientSort } from '@/components/sortable-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
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

    const { sorted, sort, onSort } = useClientSort(payslips, (p, column) => {
        switch (column) {
            case 'period':
            case 'pay_date':
                return p.pay_date;
            case 'gross_pay':
                return p.gross_pay;
            case 'total_deductions':
                return p.total_deductions;
            case 'net_pay':
                return p.net_pay;
            default:
                return null;
        }
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Payslips" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">My Payslips</h1>
                <Card className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <SortableHeader column="period" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Period
                                </SortableHeader>
                                <SortableHeader column="pay_date" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Pay date
                                </SortableHeader>
                                <SortableHeader column="gross_pay" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Gross
                                </SortableHeader>
                                <SortableHeader column="total_deductions" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Deductions
                                </SortableHeader>
                                <SortableHeader column="net_pay" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Net pay
                                </SortableHeader>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {sorted.map((p) => (
                                <tr key={p.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">{p.period}</td>
                                    <td className="px-3 py-2">{fmtDate(p.pay_date)}</td>
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
