<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'primary_assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', Rule::in(Task::PRIORITIES)],
            'start_date' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'progress_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'ceo_priority' => ['sometimes', 'boolean'],
            'confidentiality' => ['sometimes', Rule::in(Task::CONFIDENTIALITY_LEVELS)],
            'work_location' => ['sometimes', Rule::in(['unspecified', 'remote', 'office', 'onsite'])],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
        ];
    }
}
