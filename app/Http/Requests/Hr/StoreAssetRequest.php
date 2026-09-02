<?php

namespace App\Http\Requests\Hr;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function rules(): array
    {
        $assetId = $this->route('asset')?->id;

        return [
            'asset_tag' => ['required', 'string', 'max:50', Rule::unique('assets', 'asset_tag')->ignore($assetId)],
            'asset_category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'warranty_expiry' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Asset::STATUSES)],
            'condition' => ['required', Rule::in(Asset::CONDITIONS)],
            'location' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
