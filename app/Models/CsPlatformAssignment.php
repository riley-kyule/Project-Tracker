<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee's assigned platform and/or country operation — Customer
 * Service Board Requirements Specification v1.0 §5.2. HOD-controlled, with
 * an effective date and a backup employee for continuity coverage.
 */
class CsPlatformAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
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

    public function backupEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'backup_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whichever assignment(s) applied on $date — for reconstructing "who was
     * assigned to what platform/country" after a reassignment, per §12's
     * "retain the ... platform assignment effective at the time".
     */
    public function scopeEffectiveOn(Builder $query, Carbon $date): Builder
    {
        return $query
            ->where('effective_from', '<=', $date->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date->toDateString()));
    }
}
