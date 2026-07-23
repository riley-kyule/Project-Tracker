<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    protected $fillable = ['priority', 'first_response_minutes', 'resolution_minutes', 'response_gap_minutes', 'business_hours_only', 'is_active'];

    protected function casts(): array
    {
        return [
            'business_hours_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function forPriority(string $priority): ?self
    {
        return static::query()->where('priority', $priority)->where('is_active', true)->first();
    }
}
