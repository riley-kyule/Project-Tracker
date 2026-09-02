<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Only honoured for hr.leave.manage holders filing on someone's behalf.
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day_start' => ['boolean'],
            'half_day_end' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'contact_during_leave' => ['nullable', 'string', 'max:100'],
            'handover_to' => ['nullable', 'integer', 'exists:employees,id'],
            'is_emergency' => ['boolean'],
        ];
    }
}
