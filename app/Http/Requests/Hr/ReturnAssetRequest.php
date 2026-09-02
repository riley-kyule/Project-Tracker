<?php

namespace App\Http\Requests\Hr;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnAssetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'condition_in' => ['nullable', Rule::in(Asset::CONDITIONS)],
            'new_status' => ['nullable', Rule::in([Asset::STATUS_IN_STOCK, Asset::STATUS_IN_REPAIR, Asset::STATUS_RETIRED, Asset::STATUS_LOST])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
