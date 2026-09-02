<?php

namespace App\Http\Requests\Hr;

use App\Models\PerformanceGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerformanceGoalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'performance_cycle_id' => ['nullable', 'integer', 'exists:performance_cycles,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'weight' => ['nullable', 'integer', 'between:0,100'],
            'metric' => ['nullable', 'string', 'max:150'],
            'progress_pct' => ['nullable', 'integer', 'between:0,100'],
            'rating' => ['nullable', 'numeric', 'between:1,5'],
            'status' => ['nullable', Rule::in(PerformanceGoal::STATUSES)],
            'due_on' => ['nullable', 'date'],
        ];
    }
}
