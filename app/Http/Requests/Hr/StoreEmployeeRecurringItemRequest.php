<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeRecurringItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRecurringItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in([EmployeeRecurringItem::KIND_EARNING, EmployeeRecurringItem::KIND_DEDUCTION])],
            'name' => ['required', 'string', 'max:100'],
            'calc_type' => ['required', Rule::in(['fixed', 'percent_of_basic'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'is_taxable' => ['boolean'],
            'is_pretax' => ['boolean'],
            'affects_nssf' => ['boolean'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
