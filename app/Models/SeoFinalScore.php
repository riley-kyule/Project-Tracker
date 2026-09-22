<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The §2 formula's stored result for one employee/week: (avg approved daily
 * score x 70%) + (approved weekly score x 30%). Recalculated by
 * App\Services\Seo\SeoScoringService whenever an underlying daily or weekly
 * approval changes, so history/trend queries never need to replay raw cards.
 */
class SeoFinalScore extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // See SeoDailyCard::work_date — equality lookups must use whereDate().
            'week_start_date' => 'date',
            'week_end_date' => 'date',
            'avg_daily_approved_score' => 'decimal:2',
            'weekly_approved_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'is_final' => 'boolean',
            'calculated_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
