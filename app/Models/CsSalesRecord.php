<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One commercial outcome — a new sale, a renewal, or a reactivation —
 * attributed to an employee and a payment reference. Customer Service Board
 * Requirements Specification v1.0 §3/§4: the ledger CsScoringService reads
 * to compute weekly commercial achievement, and the record HOD approval
 * turns a claim into cleared, counted revenue.
 */
class CsSalesRecord extends Model
{
    public const CATEGORY_NEW = 'new';

    public const CATEGORY_RENEWAL = 'renewal';

    public const CATEGORY_REACTIVATION = 'reactivation';

    public const CATEGORIES = [self::CATEGORY_NEW, self::CATEGORY_RENEWAL, self::CATEGORY_REACTIVATION];

    public const STATUS_PENDING = 'pending';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_FRAUDULENT = 'fraudulent';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_CLEARED, self::STATUS_REVERSED, self::STATUS_REFUNDED, self::STATUS_FRAUDULENT];

    /** §4 "cleared payment": only these ever count as collected revenue. */
    public const STATUSES_NOT_COLLECTED = [self::STATUS_PENDING, self::STATUS_REVERSED, self::STATUS_REFUNDED, self::STATUS_FRAUDULENT];

    public const ATTRIBUTION_PRIMARY = 'primary';

    public const ATTRIBUTION_SHARED = 'shared';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contact_time' => 'datetime',
            'amount' => 'decimal:2',
            'reporting_currency_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'exchange_rate_date' => 'date',
            'cleared_at' => 'datetime',
            'split_percentage' => 'decimal:2',
            'week_start_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function sharedWithEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'shared_with_employee_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function evidence(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function attachments(): MorphMany
    {
        return $this->evidence();
    }

    public function isCleared(): bool
    {
        return $this->status === self::STATUS_CLEARED;
    }

    public function scopeCleared(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLEARED);
    }
}
