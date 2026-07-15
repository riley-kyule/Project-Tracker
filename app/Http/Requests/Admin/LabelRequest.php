<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabelRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Label|null $label */
        $label = $this->route('label');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('labels', 'name')->ignore($label)],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
