<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Services\Hr\Payroll\PayslipPdfBuilder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PayslipController extends Controller
{
    public function show(Payslip $payslip): Response
    {
        Gate::authorize('view', $payslip);

        $payslip->load('employee:id,first_name,middle_name,last_name,staff_number,job_title', 'period:id,label,pay_date');

        return Inertia::render('hr/payroll/payslip', [
            'payslip' => [
                ...$payslip->only([
                    'id', 'currency', 'basic_salary', 'earnings', 'gross_pay', 'pretax_deductions',
                    'taxable_pay', 'paye_before_relief', 'personal_relief', 'insurance_relief', 'paye',
                    'nssf_employee', 'nssf_employer', 'shif_employee', 'housing_levy_employee',
                    'housing_levy_employer', 'nita_employer', 'other_deductions', 'total_deductions',
                    'net_pay', 'employer_cost', 'ytd',
                ]),
                'employee' => $payslip->employee->full_name,
                'staff_number' => $payslip->employee->staff_number,
                'job_title' => $payslip->employee->job_title,
                'period' => $payslip->period->label,
            ],
        ]);
    }

    public function download(Payslip $payslip, PayslipPdfBuilder $builder): HttpResponse
    {
        Gate::authorize('view', $payslip);

        return $builder->stream($payslip);
    }
}
