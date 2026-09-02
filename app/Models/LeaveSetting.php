<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (id = 1) of org-wide leave policy toggles, surfaced in
 * Settings → Leave. Seeded values are defaults, not hardcoded behaviour.
 */
class LeaveSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accrual_enabled' => 'boolean',
            'accrual_days_per_month' => 'decimal:2',
            'carryover_enabled' => 'boolean',
            'block_same_department_overlap' => 'boolean',
            'overlap_exempt_leave_type_codes' => 'array',
            'overlap_override_roles' => 'array',
            'require_handover' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'overlap_exempt_leave_type_codes' => ['SICK', 'EMERGENCY'],
            'overlap_override_roles' => ['CEO', 'Administrator', 'HR Manager'],
        ]);
    }

    /** @return list<string> */
    public function exemptCodes(): array
    {
        return array_map('strtoupper', $this->overlap_exempt_leave_type_codes ?? []);
    }

    /** @return list<string> */
    public function overrideRoles(): array
    {
        return $this->overlap_override_roles ?? [];
    }
}
