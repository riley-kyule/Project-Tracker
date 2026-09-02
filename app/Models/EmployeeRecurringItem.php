<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmployeeRecurringItem extends Model
{
    public const KIND_EARNING = 'earning';

    public const KIND_DEDUCTION = 'deduction';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_pretax' => 'boolean',
            'affects_nssf' => 'boolean',
            'is_active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeActiveOn(Builder $query, Carbon $date): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $date->toDateString()))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date->toDateString()));
    }

    /** The amount to apply this run, respecting a loan/advance balance if set. */
    public function amountFor(float $basicSalary): float
    {
        $raw = $this->calc_type === 'percent_of_basic'
            ? round($basicSalary * ((float) $this->amount / 100), 2)
            : (float) $this->amount;

        if ($this->balance !== null) {
            return min($raw, max((float) $this->balance, 0));
        }

        return $raw;
    }
}
