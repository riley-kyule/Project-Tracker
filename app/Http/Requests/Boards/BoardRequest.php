<?php

namespace App\Http\Requests\Boards;

use App\Models\Board;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'visibility' => ['required', Rule::in([
                Board::VISIBILITY_COMPANY,
                Board::VISIBILITY_DEPARTMENT,
                Board::VISIBILITY_RESTRICTED,
            ])],
            'is_active' => ['boolean'],
        ];
    }
}
