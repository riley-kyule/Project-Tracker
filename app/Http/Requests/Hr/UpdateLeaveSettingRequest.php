<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveSettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entitlement_basis' => ['required', Rule::in(['contract_period', 'calendar_year'])],
            'leave_year_start_month' => ['required', 'integer', 'between:1,12'],
            'default_annual_days' => ['required', 'integer', 'between:0,60'],
            'accrual_enabled' => ['boolean'],
            'accrual_days_per_month' => ['required', 'numeric', 'between:0,10'],
            'carryover_enabled' => ['boolean'],
            'max_carryover_days' => ['required', 'integer', 'between:0,60'],
            'block_same_department_overlap' => ['boolean'],
            'overlap_exempt_leave_type_codes' => ['array'],
            'overlap_exempt_leave_type_codes.*' => ['string', 'max:30'],
            'overlap_override_roles' => ['array'],
            'overlap_override_roles.*' => ['string', 'exists:roles,name'],
            'min_notice_days' => ['required', 'integer', 'between:0,90'],
            'require_handover' => ['boolean'],
        ];
    }
}
