<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreStatutoryRateSetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['boolean'],

            'payload' => ['required', 'array'],
            'payload.paye_bands' => ['required', 'array', 'min:1'],
            'payload.paye_bands.*.upto' => ['nullable', 'numeric', 'min:0'],
            'payload.paye_bands.*.rate' => ['required', 'numeric', 'between:0,1'],
            'payload.personal_relief_monthly' => ['required', 'numeric', 'min:0'],
            'payload.insurance_relief.rate' => ['required', 'numeric', 'between:0,1'],
            'payload.insurance_relief.cap_monthly' => ['nullable', 'numeric', 'min:0'],
            'payload.nssf.tier1_upper' => ['required', 'numeric', 'min:0'],
            'payload.nssf.tier2_upper' => ['required', 'numeric', 'gte:payload.nssf.tier1_upper'],
            'payload.nssf.rate' => ['required', 'numeric', 'between:0,1'],
            'payload.nssf.employer_matches' => ['boolean'],
            'payload.shif.rate' => ['required', 'numeric', 'between:0,1'],
            'payload.shif.min_monthly' => ['nullable', 'numeric', 'min:0'],
            'payload.shif.cap_monthly' => ['nullable', 'numeric', 'min:0'],
            'payload.housing_levy.employee_rate' => ['required', 'numeric', 'between:0,1'],
            'payload.housing_levy.employer_rate' => ['required', 'numeric', 'between:0,1'],
            'payload.nita_levy_monthly' => ['nullable', 'numeric', 'min:0'],
            'payload.deductible_from_taxable' => ['nullable', 'array'],
        ];
    }
}
