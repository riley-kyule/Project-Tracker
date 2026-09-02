<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class DecideLeaveRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'approve' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
            'override_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
