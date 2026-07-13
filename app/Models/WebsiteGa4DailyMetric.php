<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteGa4DailyMetric extends Model
{
    protected $fillable = [
        'website_id',
        'date',
        'users',
        'sessions',
        'engaged_sessions',
        'key_events',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'key_events' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
