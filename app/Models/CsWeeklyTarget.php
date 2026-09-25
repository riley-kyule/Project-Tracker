<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The HOD's market-adjusted weekly commercial targets for one employee —
 * Customer Service Board Requirements Specification v1.0 §3. Every write
 * after the week begins is audited via AuditLogger from the owning
 * controller, per §3 "target changes after the period begins require a
 * reason and audit record".
 */
class CsWeeklyTarget extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // See CsDailyCard::work_date — equality lookups must use whereDate().
            'week_start_date' => 'date',
            'week_end_date' => 'date',
            'new_customer_revenue_target' => 'decimal:2',
            'retained_revenue_target' => 'decimal:2',
            'set_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
