<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollSettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_kra_pin' => ['nullable', 'string', 'max:20'],
            'nssf_employer_number' => ['nullable', 'string', 'max:30'],
            'shif_employer_number' => ['nullable', 'string', 'max:30'],
            'payroll_currency' => ['required', 'string', 'size:3'],
            'default_pay_day' => ['required', 'integer', 'between:1,31'],
            'nita_levy_enabled' => ['boolean'],

            'payslip_company_name' => ['nullable', 'string', 'max:150'],
            'payslip_company_address' => ['nullable', 'string', 'max:500'],
            'payslip_footer_note' => ['nullable', 'string', 'max:255'],
            'payslip_dispatch_timing' => ['required', Rule::in(['on_mark_paid', 'on_pay_date'])],
            'payroll_requires_second_approval' => ['boolean'],
        ];
    }
}
