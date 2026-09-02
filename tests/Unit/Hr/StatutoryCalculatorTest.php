<?php

namespace Tests\Unit\Hr;

use App\Services\Hr\Payroll\StatutoryCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Worked examples for the Kenyan statutory maths. The rate payload mirrors the
 * "Kenya 2026" seed. These figures are computed from the documented formula —
 * cross-check a sample against the Aren / KRA calculator before go-live.
 */
class StatutoryCalculatorTest extends TestCase
{
    private array $rates = [
        'paye_bands' => [
            ['upto' => 24000, 'rate' => 0.10],
            ['upto' => 32333, 'rate' => 0.25],
            ['upto' => 500000, 'rate' => 0.30],
            ['upto' => 800000, 'rate' => 0.325],
            ['upto' => null, 'rate' => 0.35],
        ],
        'personal_relief_monthly' => 2400,
        'insurance_relief' => ['rate' => 0.15, 'cap_monthly' => 5000],
        'nssf' => ['tier1_upper' => 9000, 'tier2_upper' => 108000, 'rate' => 0.06, 'employer_matches' => true],
        'shif' => ['rate' => 0.0275, 'min_monthly' => 300, 'cap_monthly' => null],
        'housing_levy' => ['employee_rate' => 0.015, 'employer_rate' => 0.015],
        'nita_levy_monthly' => 50,
    ];

    private function calc(): StatutoryCalculator
    {
        return new StatutoryCalculator;
    }

    public function test_nssf_tiers_and_employer_match(): void
    {
        // Below tier 1: 6% of the whole amount.
        $this->assertEqualsWithDelta(300.0, $this->calc()->nssf(5000, $this->rates)['employee'], 0.01);

        // 50,000 pensionable: 6% × 9,000 + 6% × 41,000 = 540 + 2,460 = 3,000.
        $r = $this->calc()->nssf(50000, $this->rates);
        $this->assertEqualsWithDelta(3000.0, $r['employee'], 0.01);
        $this->assertEqualsWithDelta(3000.0, $r['employer'], 0.01);

        // At/above the UEL the employee contribution caps at 6% × 108,000 = 6,480.
        $this->assertEqualsWithDelta(6480.0, $this->calc()->nssf(150000, $this->rates)['employee'], 0.01);
    }

    public function test_shif_percentage_with_floor(): void
    {
        $this->assertEqualsWithDelta(2750.0, $this->calc()->shif(100000, $this->rates), 0.01);
        // Floor of 300 bites on very low pay.
        $this->assertEqualsWithDelta(300.0, $this->calc()->shif(5000, $this->rates), 0.01);
    }

    public function test_housing_levy_is_1_5_percent_each_side(): void
    {
        $ahl = $this->calc()->housingLevy(100000, $this->rates);
        $this->assertEqualsWithDelta(1500.0, $ahl['employee'], 0.01);
        $this->assertEqualsWithDelta(1500.0, $ahl['employer'], 0.01);
    }

    public function test_insurance_relief_is_capped(): void
    {
        $this->assertEqualsWithDelta(1500.0, $this->calc()->insuranceRelief(10000, $this->rates), 0.01);
        $this->assertEqualsWithDelta(5000.0, $this->calc()->insuranceRelief(60000, $this->rates), 0.01);
    }

    public function test_paye_band_walk_for_100k_gross(): void
    {
        // Gross 100,000 → statutory pre-tax: NSSF 6,000 + SHIF 2,750 + AHL 1,500 = 10,250.
        // Taxable pay = 89,750.
        $taxable = 100000 - (6000 + 2750 + 1500);
        $this->assertEquals(89750, $taxable);

        $paye = $this->calc()->paye($taxable, $this->rates);

        // 10%×24,000 + 25%×8,333 + 30%×57,417 = 2,400 + 2,083.25 + 17,225.10
        $this->assertEqualsWithDelta(21708.35, $paye['tax_before_relief'], 0.01);

        $due = max($paye['tax_before_relief'] - $this->calc()->personalRelief($this->rates), 0);
        $this->assertEqualsWithDelta(19308.35, $due, 0.01);
    }

    public function test_low_earner_pays_only_the_first_band_and_relief_can_wipe_paye(): void
    {
        // Taxable 20,000 → 10% = 2,000, personal relief 2,400 → PAYE nil.
        $paye = $this->calc()->paye(20000, $this->rates);
        $this->assertEqualsWithDelta(2000.0, $paye['tax_before_relief'], 0.01);
        $this->assertEqualsWithDelta(0.0, max($paye['tax_before_relief'] - 2400, 0), 0.01);
    }

    public function test_top_bracket_is_reached(): void
    {
        $paye = $this->calc()->paye(1_000_000, $this->rates);
        // last band line should be 35% on (1,000,000 - 800,000)
        $last = end($paye['bands']);
        $this->assertSame(0.35, $last['rate']);
        $this->assertEqualsWithDelta(200000.0, $last['amount'], 0.01);
    }
}
