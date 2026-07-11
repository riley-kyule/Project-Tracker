<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'manager_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::notIn([$this->route('user')?->id]),
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'role' => ['required', 'string', 'exists:roles,name'],
        ];
    }
}
