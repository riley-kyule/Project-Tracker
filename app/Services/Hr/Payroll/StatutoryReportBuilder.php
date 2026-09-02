<?php

namespace App\Services\Hr\Payroll;

use App\Models\PayrollPeriod;
use App\Models\Payslip;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employer statutory schedules for a payroll period, as CSV — the format the
 * KRA iTax / SHA / bank bulk-upload templates expect.
 */
class StatutoryReportBuilder
{
    public const REPORTS = ['paye', 'nssf', 'shif', 'housing_levy', 'nita', 'bank_schedule', 'muster_roll'];

    public function download(PayrollPeriod $period, string $report): StreamedResponse
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);

        $rows = $period->payslips()
            ->with('employee:id,first_name,middle_name,last_name,staff_number,kra_pin,nssf_number,shif_number,national_id_number,bank_name,bank_branch,bank_account_number,bank_account_name,payment_method,mpesa_number')
            ->get();

        [$headers, $data] = $this->build($report, $rows);
        $filename = "{$report}-{$period->year}-{$period->month}.csv";

        return response()->streamDownload(function () use ($headers, $data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($data as $line) {
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: list<string>, 1: list<list<mixed>>} */
    private function build(string $report, Collection $payslips): array
    {
        return match ($report) {
            'paye' => [
                ['PIN', 'Employee Name', 'Gross Pay', 'Taxable Pay', 'Tax Payable', 'PAYE', 'Personal Relief', 'Insurance Relief'],
                $payslips->map(fn (Payslip $p) => [
                    $p->employee->kra_pin, $p->employee->full_name, $p->gross_pay, $p->taxable_pay,
                    $p->paye_before_relief, $p->paye, $p->personal_relief, $p->insurance_relief,
                ])->all(),
            ],
            'nssf' => [
                ['NSSF No', 'Employee Name', 'ID No', 'Gross Pay', 'Employee Contribution', 'Employer Contribution', 'Total'],
                $payslips->map(fn (Payslip $p) => [
                    $p->employee->nssf_number, $p->employee->full_name, $p->employee->national_id_number,
                    $p->gross_pay, $p->nssf_employee, $p->nssf_employer,
                    round((float) $p->nssf_employee + (float) $p->nssf_employer, 2),
                ])->all(),
            ],
            'shif' => [
                ['SHA No', 'Employee Name', 'ID No', 'Gross Pay', 'SHIF Contribution'],
                $payslips->map(fn (Payslip $p) => [
                    $p->employee->shif_number, $p->employee->full_name, $p->employee->national_id_number,
                    $p->gross_pay, $p->shif_employee,
                ])->all(),
            ],
            'housing_levy' => [
                ['PIN', 'ID No', 'Employee Name', 'Gross Pay', 'Employee Levy', 'Employer Levy', 'Total'],
                $payslips->map(fn (Payslip $p) => [
                    $p->employee->kra_pin, $p->employee->national_id_number, $p->employee->full_name,
                    $p->gross_pay, $p->housing_levy_employee, $p->housing_levy_employer,
                    round((float) $p->housing_levy_employee + (float) $p->housing_levy_employer, 2),
                ])->all(),
            ],
            'nita' => [
                ['Employee Name', 'ID No', 'NITA Levy'],
                $payslips->map(fn (Payslip $p) => [$p->employee->full_name, $p->employee->national_id_number, $p->nita_employer])->all(),
            ],
            'bank_schedule' => [
                ['Employee Name', 'Bank', 'Branch', 'Account Name', 'Account Number', 'Payment Method', 'Net Pay'],
                $payslips->map(fn (Payslip $p) => [
                    $p->employee->full_name, $p->employee->bank_name, $p->employee->bank_branch,
                    $p->employee->bank_account_name, $p->employee->bank_account_number,
                    $p->employee->payment_method === 'mpesa' ? "M-Pesa {$p->employee->mpesa_number}" : $p->employee->payment_method,
                    $p->net_pay,
                ])->all(),
            ],
            'muster_roll' => [
                ['Staff No', 'Employee Name', 'Basic', 'Gross', 'PAYE', 'NSSF', 'SHIF', 'Housing Levy', 'Other Deductions', 'Net Pay', 'Employer Cost'],
                $payslips->map(fn (Payslip $p) => [
                    $p->employee->staff_number, $p->employee->full_name, $p->basic_salary, $p->gross_pay, $p->paye,
                    $p->nssf_employee, $p->shif_employee, $p->housing_levy_employee,
                    collect($p->other_deductions ?? [])->sum('amount'), $p->net_pay, $p->employer_cost,
                ])->all(),
            ],
        };
    }
}
