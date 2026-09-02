<?php

namespace App\Services\Hr\Payroll;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\EmployeeRecurringItem;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\StatutoryRateSet;
use Illuminate\Support\Facades\DB;

/**
 * Builds every payslip for a payroll period from the compensation, recurring
 * items and statutory rate set in force. Deterministic — re-running a period
 * (while it's still editable) replaces its payslips.
 */
class PayrollRunner
{
    public function __construct(private readonly StatutoryCalculator $calc) {}

    public function run(PayrollPeriod $period): int
    {
        $rateSet = $period->rateSet ?? StatutoryRateSet::inForceOn($period->end_date);

        abort_if($rateSet === null, 422, 'No statutory rate set is in force for this period.');

        $rates = $rateSet->payload;
        $nitaEnabled = (bool) (CompanySetting::current()->nita_levy_enabled ?? true);

        $employees = Employee::query()
            ->where('employment_status', '!=', Employee::STATUS_TERMINATED)
            ->with(['recurringItems' => fn ($q) => $q->activeOn($period->end_date)])
            ->get();

        $count = 0;

        DB::transaction(function () use ($period, $rateSet, $rates, $nitaEnabled, $employees, &$count) {
            $period->update(['statutory_rate_set_id' => $rateSet->id]);
            $period->payslips()->delete();

            foreach ($employees as $employee) {
                $compensation = $employee->compensationOn($period->end_date);
                if ($compensation === null) {
                    continue; // no salary on file — skip rather than pay zero
                }

                $this->buildPayslip($period, $employee, $compensation, $rates, $nitaEnabled);
                $count++;
            }
        });

        return $count;
    }

    private function buildPayslip(PayrollPeriod $period, Employee $employee, $compensation, array $rates, bool $nitaEnabled): Payslip
    {
        $basic = (float) $compensation->basic_salary;

        $earnings = [['name' => 'Basic salary', 'amount' => $basic, 'taxable' => true]];
        $pensionable = $basic;

        foreach ($compensation->allowanceLines() as $line) {
            $earnings[] = ['name' => $line['name'], 'amount' => $line['amount'], 'taxable' => $line['taxable']];
            if ($line['pensionable']) {
                $pensionable += $line['amount'];
            }
        }

        $pretaxDeductions = [];
        $otherDeductions = [];
        $loanRepayments = [];

        foreach ($employee->recurringItems as $item) {
            $amount = $item->amountFor($basic);
            if ($amount <= 0) {
                continue;
            }

            if ($item->kind === EmployeeRecurringItem::KIND_EARNING) {
                $earnings[] = ['name' => $item->name, 'amount' => $amount, 'taxable' => $item->is_taxable];
                if ($item->affects_nssf) {
                    $pensionable += $amount;
                }
            } elseif ($item->is_pretax) {
                $pretaxDeductions[] = ['name' => $item->name, 'amount' => $amount];
            } else {
                $otherDeductions[] = ['name' => $item->name, 'amount' => $amount];
                if ($item->balance !== null) {
                    $loanRepayments[$item->id] = $amount;
                }
            }
        }

        $gross = array_sum(array_column($earnings, 'amount'));
        $taxableEarnings = array_sum(array_map(fn ($e) => $e['taxable'] ? $e['amount'] : 0, $earnings));

        $nssf = $this->calc->nssf($pensionable, $rates);
        $shif = $this->calc->shif($gross, $rates);
        $ahl = $this->calc->housingLevy($gross, $rates);
        $deductible = $this->calc->deductibleFromTaxable($rates);

        $statutoryPretax = 0.0;
        if ($deductible['nssf'] ?? true) {
            $statutoryPretax += $nssf['employee'];
        }
        if ($deductible['shif'] ?? true) {
            $statutoryPretax += $shif;
        }
        if ($deductible['housing_levy'] ?? true) {
            $statutoryPretax += $ahl['employee'];
        }

        $pretaxTotal = $statutoryPretax + array_sum(array_column($pretaxDeductions, 'amount'));
        $taxablePay = max($taxableEarnings - $pretaxTotal, 0);

        $paye = $this->calc->paye($taxablePay, $rates);
        $personalRelief = $this->calc->personalRelief($rates);
        $insuranceRelief = 0.0; // premiums tracked as recurring items in a future iteration
        $payeDue = max($paye['tax_before_relief'] - $personalRelief - $insuranceRelief, 0);

        $nita = $nitaEnabled ? $this->calc->nitaLevy($rates) : 0.0;

        $totalDeductions = $payeDue + $nssf['employee'] + $shif + $ahl['employee']
            + array_sum(array_column($pretaxDeductions, 'amount'))
            + array_sum(array_column($otherDeductions, 'amount'));

        $netPay = $gross - $totalDeductions;
        $employerCost = $gross + $nssf['employer'] + $ahl['employer'] + $nita;

        $payslip = $period->payslips()->create([
            'employee_id' => $employee->id,
            'currency' => $compensation->currency,
            'basic_salary' => $basic,
            'earnings' => $earnings,
            'gross_pay' => round($gross, 2),
            'pretax_deductions' => $pretaxDeductions,
            'taxable_pay' => round($taxablePay, 2),
            'paye_before_relief' => $paye['tax_before_relief'],
            'personal_relief' => $personalRelief,
            'insurance_relief' => $insuranceRelief,
            'paye' => round($payeDue, 2),
            'nssf_employee' => $nssf['employee'],
            'nssf_employer' => $nssf['employer'],
            'shif_employee' => $shif,
            'housing_levy_employee' => $ahl['employee'],
            'housing_levy_employer' => $ahl['employer'],
            'nita_employer' => $nita,
            'other_deductions' => $otherDeductions,
            'total_deductions' => round($totalDeductions, 2),
            'net_pay' => round($netPay, 2),
            'employer_cost' => round($employerCost, 2),
            'ytd' => $this->ytdFor($employee, $period, round($gross, 2), round($payeDue, 2), $nssf['employee'], $shif, $ahl['employee']),
        ]);

        foreach ($loanRepayments as $itemId => $amount) {
            EmployeeRecurringItem::query()->whereKey($itemId)->decrement('balance', $amount);
            EmployeeRecurringItem::query()->whereKey($itemId)->where('balance', '<=', 0)->update(['is_active' => false]);
        }

        return $payslip;
    }

    /** Year-to-date cumulative totals, this period included, within the tax year. */
    private function ytdFor(Employee $employee, PayrollPeriod $period, float $gross, float $paye, float $nssf, float $shif, float $ahl): array
    {
        $prior = Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereHas('period', fn ($q) => $q->where('year', $period->year)->where('month', '<', $period->month))
            ->get();

        return [
            'gross' => round($prior->sum('gross_pay') + $gross, 2),
            'paye' => round($prior->sum('paye') + $paye, 2),
            'nssf' => round($prior->sum('nssf_employee') + $nssf, 2),
            'shif' => round($prior->sum('shif_employee') + $shif, 2),
            'housing_levy' => round($prior->sum('housing_levy_employee') + $ahl, 2),
        ];
    }
}
