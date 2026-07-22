<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function rules(): array
    {
        // Shared by store (POST) and update (PATCH). Update is used for single-field
        // patches from the project page (e.g. just {status: 'active'}), so those
        // fields can't be unconditionally 'required' there or every partial save
        // 422s on the other, absent fields.
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'owner_id' => [$required, 'integer', 'exists:users,id'],
            'status' => [$required, Rule::in(Project::STATUSES)],
            'health_status' => [$required, Rule::in(Project::HEALTH_STATUSES)],
            'priority' => [$required, Rule::in(['critical', 'high', 'medium', 'low'])],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'progress_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'country_ids' => ['sometimes', 'array'],
            'country_ids.*' => ['integer', 'exists:countries,id'],
            'website_ids' => ['sometimes', 'array'],
            'website_ids.*' => ['integer', 'exists:websites,id'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ];
    }
}
