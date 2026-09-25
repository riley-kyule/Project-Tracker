<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One employee's Customer Service daily card for one workday (Customer
 * Service Board Requirements Specification v1.0 §5). Never overwritten or
 * deleted on rollover — the midnight close
 * (App\Services\Cs\CsCardLifecycleService) creates a new row for the next
 * applicable workday instead of mutating this one. Mirrors SeoDailyCard.
 */
class CsDailyCard extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REOPENED = 'reopened';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // Stored as a full "Y-m-d H:i:s" string regardless of this cast —
            // every equality lookup against this column elsewhere MUST use
            // whereDate(), never where(). See SeoDailyCard::work_date.
            'work_date' => 'date',
            'employee_submitted_points' => 'decimal:2',
            'approved_points' => 'decimal:2',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'closed_snapshot' => 'array',
            'reopened_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function items(): MorphMany
    {
        return $this->morphMany(CsCardItem::class, 'cardable')->orderBy('position');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_REOPENED]);
    }
}
