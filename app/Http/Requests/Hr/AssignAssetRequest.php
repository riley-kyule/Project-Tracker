<?php

namespace App\Http\Requests\Hr;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignAssetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'expected_return_at' => ['nullable', 'date', 'after:today'],
            'condition_out' => ['nullable', Rule::in(Asset::CONDITIONS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
