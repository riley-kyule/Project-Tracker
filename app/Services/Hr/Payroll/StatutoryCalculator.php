<?php

namespace App\Services\Hr\Payroll;

use App\Models\StatutoryRateSet;

/**
 * Kenyan statutory payroll maths — PAYE, NSSF, SHIF, Affordable Housing Levy,
 * personal & insurance relief. Every rate comes from the rate-set payload
 * (see {@see StatutoryRateSet}); nothing is hardcoded here.
 *
 * Pure and stateless: no database, no clock. All amounts are monthly KES.
 *
 * !! The seeded rate set reflects the documented 2026 position (see the HR
 * plan's Sources). Reconcile a sample run against the Aren / KRA calculator
 * before the first live payroll. !!
 */
class StatutoryCalculator
{
    /**
     * @param  array<string,mixed>  $rates  a StatutoryRateSet payload
     * @return array{tax_before_relief: float, bands: list<array{rate: float, amount: float, tax: float}>}
     */
    public function paye(float $taxablePay, array $rates): array
    {
        $taxablePay = max($taxablePay, 0.0);
        $lower = 0.0;
        $taxBeforeRelief = 0.0;
        $lines = [];

        foreach ($rates['paye_bands'] as $band) {
            $upper = $band['upto'] ?? INF;
            if ($taxablePay <= $lower) {
                break;
            }
            $inBand = min($taxablePay, $upper) - $lower;
            $tax = round($inBand * $band['rate'], 2);
            $taxBeforeRelief += $tax;
            $lines[] = ['rate' => (float) $band['rate'], 'amount' => round($inBand, 2), 'tax' => $tax];
            $lower = $upper;
        }

        return ['tax_before_relief' => round($taxBeforeRelief, 2), 'bands' => $lines];
    }

    public function personalRelief(array $rates): float
    {
        return (float) ($rates['personal_relief_monthly'] ?? 0);
    }

    /** 15% (configurable) of life/health/education premiums, capped monthly. */
    public function insuranceRelief(float $premiumsPaid, array $rates): float
    {
        $cfg = $rates['insurance_relief'] ?? [];
        $relief = $premiumsPaid * (float) ($cfg['rate'] ?? 0);

        return round(min($relief, (float) ($cfg['cap_monthly'] ?? INF)), 2);
    }

    /**
     * @return array{employee: float, employer: float, tier1: float, tier2: float}
     */
    public function nssf(float $pensionablePay, array $rates): array
    {
        $cfg = $rates['nssf'] ?? [];
        $rate = (float) ($cfg['rate'] ?? 0);
        $t1Upper = (float) ($cfg['tier1_upper'] ?? 0);
        $t2Upper = (float) ($cfg['tier2_upper'] ?? 0);

        $pensionablePay = max($pensionablePay, 0.0);
        $tier1 = round(min($pensionablePay, $t1Upper) * $rate, 2);
        $tier2 = round((min($pensionablePay, $t2Upper) - min($pensionablePay, $t1Upper)) * $rate, 2);
        $employee = $tier1 + $tier2;

        return [
            'employee' => $employee,
            'employer' => ($cfg['employer_matches'] ?? true) ? $employee : 0.0,
            'tier1' => $tier1,
            'tier2' => $tier2,
        ];
    }

    /** SHIF (SHA): a flat percentage of gross with a monthly floor and optional cap. */
    public function shif(float $grossPay, array $rates): float
    {
        $cfg = $rates['shif'] ?? [];
        $amount = $grossPay * (float) ($cfg['rate'] ?? 0);
        $amount = max($amount, (float) ($cfg['min_monthly'] ?? 0));

        if (($cfg['cap_monthly'] ?? null) !== null) {
            $amount = min($amount, (float) $cfg['cap_monthly']);
        }

        return round($amount, 2);
    }

    /**
     * Affordable Housing Levy — employee + matched employer, on gross, no cap.
     *
     * @return array{employee: float, employer: float}
     */
    public function housingLevy(float $grossPay, array $rates): array
    {
        $cfg = $rates['housing_levy'] ?? [];

        return [
            'employee' => round($grossPay * (float) ($cfg['employee_rate'] ?? 0), 2),
            'employer' => round($grossPay * (float) ($cfg['employer_rate'] ?? 0), 2),
        ];
    }

    public function nitaLevy(array $rates): float
    {
        return (float) ($rates['nita_levy_monthly'] ?? 0);
    }

    /** @return array<string,bool> which statutory contributions reduce taxable pay */
    public function deductibleFromTaxable(array $rates): array
    {
        return array_merge(
            ['nssf' => true, 'shif' => true, 'housing_levy' => true, 'post_retirement_medical' => true],
            $rates['deductible_from_taxable'] ?? [],
        );
    }
}
