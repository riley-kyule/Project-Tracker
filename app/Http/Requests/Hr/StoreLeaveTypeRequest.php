<?php

namespace App\Http\Requests\Hr;

use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('leaveType')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('leave_types', 'code')->ignore($id)],
            'is_paid' => ['boolean'],
            'accrual_method' => ['required', Rule::in(LeaveType::ACCRUAL_METHODS)],
            'default_days' => ['nullable', 'numeric', 'between:0,366'],
            'gender_eligibility' => ['nullable', Rule::in(['male', 'female'])],
            'counts_toward_overlap_block' => ['boolean'],
            'is_emergency' => ['boolean'],
            'requires_document' => ['boolean'],
            'requires_approval' => ['boolean'],
            'min_notice_days' => ['nullable', 'integer', 'between:0,90'],
            'is_active' => ['boolean'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
