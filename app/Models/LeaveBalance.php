<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'entitled_days' => 'decimal:2',
            'carried_over_days' => 'decimal:2',
            'accrued_days' => 'decimal:2',
            'taken_days' => 'decimal:2',
            'pending_days' => 'decimal:2',
            'adjustment_days' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /** Days still available to book. */
    public function getAvailableDaysAttribute(): float
    {
        return (float) $this->entitled_days
            + (float) $this->carried_over_days
            + (float) $this->accrued_days
            + (float) $this->adjustment_days
            - (float) $this->taken_days
            - (float) $this->pending_days;
    }
}
