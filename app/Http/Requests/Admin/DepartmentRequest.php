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

        // Update is also used for single-field patches (e.g. just {member_ids: [...]}
        // from the manage-members dialog), so 'name' can't be unconditionally
        // required there or every partial save 422s on the other, absent fields.
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [
                $required,
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
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
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

    /** Null when this request doesn't touch 'name' (e.g. a member_ids-only patch) — nothing to re-slug. */
    public function slug(): ?string
    {
        return $this->has('name') ? Str::slug($this->validated('name')) : null;
    }
}
