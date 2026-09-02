<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformanceReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'self_assessment' => ['nullable', 'array'],
            'self_assessment.summary' => ['nullable', 'string', 'max:4000'],
            'self_assessment.achievements' => ['nullable', 'string', 'max:4000'],
            'self_assessment.challenges' => ['nullable', 'string', 'max:4000'],

            'manager_assessment' => ['nullable', 'array'],
            'manager_assessment.summary' => ['nullable', 'string', 'max:4000'],
            'manager_assessment.strengths' => ['nullable', 'string', 'max:4000'],
            'manager_assessment.development' => ['nullable', 'string', 'max:4000'],

            'overall_rating' => ['nullable', 'numeric', 'between:1,5'],
        ];
    }
}
