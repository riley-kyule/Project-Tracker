<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A configurable, additional report-recipient email for a department —
 * beyond its manager_id/assistant_manager_id — per SEO Board Requirements
 * Specification v1.1 §4.2.1. Effective-dated so a past report's audience can
 * be reconstructed after this list changes; every write is audited via
 * AuditLogger from the owning controller.
 */
class DepartmentNotificationRecipient extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActiveOn(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query->where('is_active', true)
            ->where('effective_from', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at));
    }
}
