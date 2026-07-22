<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'primary_assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ];
    }
}
