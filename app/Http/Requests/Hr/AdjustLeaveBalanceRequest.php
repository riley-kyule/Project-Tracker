<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class AdjustLeaveBalanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entitled_days' => ['required', 'numeric', 'between:0,366'],
            'adjustment_days' => ['required', 'numeric', 'between:-60,60'],
            'adjustment_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
