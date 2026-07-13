<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSyncLog extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const SOURCE_GA4 = 'ga4';

    protected $fillable = [
        'source',
        'website_id',
        'status',
        'records_processed',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
