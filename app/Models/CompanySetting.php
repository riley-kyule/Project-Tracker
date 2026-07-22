<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'ceo_summary_time',
        'ceo_summary_last_sent_on',
    ];

    protected function casts(): array
    {
        return [
            'ceo_summary_last_sent_on' => 'date',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
