<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeaveType extends Model
{
    public const ACCRUAL_METHODS = ['entitlement', 'monthly_accrual', 'none'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'default_days' => 'decimal:1',
            'counts_toward_overlap_block' => 'boolean',
            'is_emergency' => 'boolean',
            'requires_document' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (LeaveType $type) {
            $type->code = Str::upper($type->code ?: Str::slug($type->name, '_'));
        });
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isAvailableTo(Employee $employee): bool
    {
        return $this->gender_eligibility === null
            || strcasecmp((string) $employee->gender, $this->gender_eligibility) === 0;
    }
}
