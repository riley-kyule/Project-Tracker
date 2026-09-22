<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One employee's SEO weekly card for one work week (SEO Board Requirements
 * Specification v1.1 §5). The HOD must approve the plan (plan_approved_at)
 * before the week begins; items are then locked to in-period adjustments
 * that require a reason, per §9 workflow rule 7.
 */
class SeoWeeklyCard extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PLAN_APPROVED = 'plan_approved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REOPENED = 'reopened';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // See SeoDailyCard::work_date — equality lookups must use whereDate().
            'week_start_date' => 'date',
            'week_end_date' => 'date',
            'plan_approved_at' => 'datetime',
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

    public function planApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plan_approved_by');
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
        return $this->morphMany(SeoCardItem::class, 'cardable')->orderBy('position');
    }

    public function isPlanApproved(): bool
    {
        return $this->plan_approved_at !== null;
    }
}
