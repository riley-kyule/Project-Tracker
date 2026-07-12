<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TimeEntry extends Model
{
    use HasFactory;

    public const SOURCE_TIMER = 'timer';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IMPORTED = 'imported';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'trackable_type',
        'trackable_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'source',
        'work_location',
        'adjustment_status',
        'adjustment_reason',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRunning(): bool
    {
        return $this->started_at !== null && $this->ended_at === null;
    }

    /** Counts toward totals once recorded (timer) or once a manager approves it (manual). */
    public function countsTowardTotal(): bool
    {
        return $this->source !== self::SOURCE_MANUAL || $this->adjustment_status === self::STATUS_APPROVED;
    }
}
