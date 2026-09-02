<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    /** Every employee reports to someone unless they are flagged as the org head. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('is_org_head') && blank($this->input('manager_id'))) {
                $validator->errors()->add('manager_id', 'Select who this employee reports to (or mark them as the org head).');
            }
        });
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id;

        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('employees', 'user_id')->ignore($employeeId)],
            'staff_number' => ['required', 'string', 'max:50', Rule::unique('employees', 'staff_number')->ignore($employeeId)],

            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:20'],
            'marital_status' => ['nullable', 'string', 'max:20'],

            'national_id_number' => ['nullable', 'string', 'max:20'],
            // KRA PINs are a letter, 9 digits, then a letter (e.g. A012345678Z).
            'kra_pin' => ['nullable', 'string', 'regex:/^[A-Za-z]\d{9}[A-Za-z]$/'],
            'nssf_number' => ['nullable', 'string', 'max:30'],
            'shif_number' => ['nullable', 'string', 'max:30'],
            'insurance_membership_number' => ['nullable', 'string', 'max:50'],

            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'postal_address' => ['nullable', 'string', 'max:255'],
            'physical_address' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:100'],

            // Every employee belongs to a department and reports to someone —
            // the sole exception is the org head (is_org_head), who has no manager.
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'employment_type' => ['required', Rule::in(Employee::EMPLOYMENT_TYPES)],
            'is_org_head' => ['boolean'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id', Rule::notIn(array_filter([$employeeId]))],
            'date_hired' => ['nullable', 'date'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'probation_end_date' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in([
                Employee::STATUS_ACTIVE, Employee::STATUS_ON_PROBATION, Employee::STATUS_ON_LEAVE,
                Employee::STATUS_SUSPENDED, Employee::STATUS_TERMINATED,
            ])],
            'termination_date' => ['nullable', 'date', 'required_if:employment_status,terminated'],
            'termination_reason' => ['nullable', 'string', 'max:1000'],
            'rehire_eligible' => ['boolean'],

            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', Rule::in(Employee::PAYMENT_METHODS)],
            'mpesa_number' => ['nullable', 'string', 'max:30'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
