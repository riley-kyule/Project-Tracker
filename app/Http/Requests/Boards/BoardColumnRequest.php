<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardColumnRequest extends FormRequest
{
    public const SEMANTIC_STATUSES = [
        'idea', 'backlog', 'ready', 'active', 'blocked', 'review', 'completed', 'archived', 'custom',
    ];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'semantic_status' => ['required', Rule::in(self::SEMANTIC_STATUSES)],
            'wip_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
