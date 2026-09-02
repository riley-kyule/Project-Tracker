<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeCompensationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'array'],
            'allowances.*.name' => ['required_with:allowances', 'string', 'max:100'],
            'allowances.*.amount' => ['required_with:allowances', 'numeric', 'min:0'],
            'allowances.*.taxable' => ['boolean'],
            'allowances.*.pensionable' => ['boolean'],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
