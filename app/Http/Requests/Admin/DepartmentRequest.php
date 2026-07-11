<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Model|null $department */
        $department = $this->route('department');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function slug(): string
    {
        return Str::slug($this->validated('name'));
    }
}
