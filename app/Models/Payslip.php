<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'earnings' => 'array',
            'pretax_deductions' => 'array',
            'other_deductions' => 'array',
            'ytd' => 'array',
            'basic_salary' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'non_cash_benefits' => 'decimal:2',
            'taxable_pay' => 'decimal:2',
            'paye_before_relief' => 'decimal:2',
            'personal_relief' => 'decimal:2',
            'insurance_relief' => 'decimal:2',
            'paye' => 'decimal:2',
            'nssf_employee' => 'decimal:2',
            'nssf_employer' => 'decimal:2',
            'shif_employee' => 'decimal:2',
            'housing_levy_employee' => 'decimal:2',
            'housing_levy_employer' => 'decimal:2',
            'nita_employer' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'employer_cost' => 'decimal:2',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
