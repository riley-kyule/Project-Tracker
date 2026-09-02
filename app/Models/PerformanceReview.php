<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_SELF_REVIEW = 'self_review';

    public const STATUS_MANAGER_REVIEW = 'manager_review';

    public const STATUS_SHARED = 'shared';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_CLOSED = 'closed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'self_assessment' => 'array',
            'manager_assessment' => 'array',
            'overall_rating' => 'decimal:2',
            'submitted_at' => 'datetime',
            'shared_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
