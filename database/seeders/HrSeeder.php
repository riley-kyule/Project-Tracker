<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\StatutoryRateSet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * HR module reference data.
 */
class HrSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Laptop', 'Desktop', 'Phone', 'Monitor', 'Peripheral', 'Furniture', 'Software License', 'Vehicle', 'Other'] as $name) {
            AssetCategory::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]);
        }

        LeaveSetting::current(); // create the singleton with its defaults

        // Kenya Employment Act defaults — all editable in Settings → Leave.
        $types = [
            ['name' => 'Annual Leave', 'code' => 'ANNUAL', 'default_days' => 21, 'accrual_method' => 'entitlement', 'color' => '#2563eb'],
            ['name' => 'Sick Leave', 'code' => 'SICK', 'default_days' => 14, 'accrual_method' => 'entitlement', 'counts_toward_overlap_block' => false, 'requires_document' => true, 'min_notice_days' => 0, 'color' => '#dc2626'],
            ['name' => 'Compassionate Leave', 'code' => 'COMPASSIONATE', 'default_days' => null, 'accrual_method' => 'none', 'counts_toward_overlap_block' => false, 'color' => '#7c3aed'],
            ['name' => 'Maternity Leave', 'code' => 'MATERNITY', 'default_days' => 90, 'accrual_method' => 'none', 'gender_eligibility' => 'female', 'counts_toward_overlap_block' => false, 'color' => '#db2777'],
            ['name' => 'Paternity Leave', 'code' => 'PATERNITY', 'default_days' => 14, 'accrual_method' => 'none', 'gender_eligibility' => 'male', 'counts_toward_overlap_block' => false, 'color' => '#0891b2'],
            ['name' => 'Emergency Leave', 'code' => 'EMERGENCY', 'default_days' => null, 'accrual_method' => 'none', 'is_emergency' => true, 'counts_toward_overlap_block' => false, 'color' => '#ea580c'],
            ['name' => 'Unpaid Leave', 'code' => 'UNPAID', 'default_days' => null, 'accrual_method' => 'none', 'is_paid' => false, 'color' => '#64748b'],
            ['name' => 'Study Leave', 'code' => 'STUDY', 'default_days' => null, 'accrual_method' => 'none', 'color' => '#65a30d'],
        ];

        foreach ($types as $type) {
            LeaveType::query()->updateOrCreate(['code' => $type['code']], $type);
        }

        // Kenyan public holidays with fixed calendar dates recur every year.
        $recurring = [
            ['name' => "New Year's Day", 'date' => '2026-01-01'],
            ['name' => 'Labour Day', 'date' => '2026-05-01'],
            ['name' => 'Madaraka Day', 'date' => '2026-06-01'],
            ['name' => 'Mazingira Day', 'date' => '2026-10-10'],
            ['name' => 'Mashujaa Day', 'date' => '2026-10-20'],
            ['name' => 'Jamhuri Day', 'date' => '2026-12-12'],
            ['name' => 'Christmas Day', 'date' => '2026-12-25'],
            ['name' => 'Boxing Day', 'date' => '2026-12-26'],
        ];

        foreach ($recurring as $holiday) {
            PublicHoliday::query()->updateOrCreate(
                ['name' => $holiday['name'], 'date' => $holiday['date']],
                ['is_recurring' => true, 'country' => 'KE'],
            );
        }

        // Default Kenyan statutory rate set. Reflects the documented 2026
        // position (see the HR plan's Sources) — reconcile a sample run against
        // the Aren / KRA calculator before running live payroll.
        StatutoryRateSet::query()->firstOrCreate(
            ['name' => 'Kenya 2026'],
            [
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'is_active' => true,
                'payload' => [
                    'currency' => 'KES',
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
                    'deductible_from_taxable' => ['nssf' => true, 'shif' => true, 'housing_levy' => true, 'post_retirement_medical' => true],
                ],
            ],
        );
    }
}
