<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DepartmentRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Department|null $department */
        $department = $this->route('department');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_department_id' => array_filter([
                'nullable',
                'integer',
                'exists:departments,id',
                $department ? Rule::notIn([$department->id]) : null,
            ]),
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'assistant_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
            'daily_summary_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Keep department hierarchy to two levels: a chosen parent must not
     * itself have a parent, and a department that already has children
     * cannot be made a child of another department.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentId = $this->input('parent_department_id');

            if (! $parentId) {
                return;
            }

            $parent = Department::query()->find($parentId);

            if ($parent && $parent->parent_department_id !== null) {
                $validator->errors()->add('parent_department_id', 'A sub-department cannot itself have sub-departments.');
            }

            /** @var Department|null $department */
            $department = $this->route('department');

            if ($department && $department->children()->exists()) {
                $validator->errors()->add('parent_department_id', 'This department already has sub-departments and cannot become one itself.');
            }
        });
    }

    public function slug(): string
    {
        return Str::slug($this->validated('name'));
    }
}
