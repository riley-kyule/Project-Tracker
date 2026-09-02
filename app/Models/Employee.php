<?php

namespace App\Models;

use App\Policies\EmployeePolicy;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * The HR system of record for a person's employment. Linked to a {@see User}
 * only when they also hold a platform login (`user_id`); casual/contract staff
 * and leavers keep an employee record with no account.
 */
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_PROBATION = 'on_probation';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_TERMINATED = 'terminated';

    public const EMPLOYMENT_TYPES = ['permanent', 'contract', 'casual', 'intern'];

    public const PAYMENT_METHODS = ['bank', 'mpesa', 'cash', 'cheque'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_hired' => 'date',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'probation_end_date' => 'date',
            'termination_date' => 'date',
            'rehire_eligible' => 'boolean',
            'is_org_head' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function nextOfKin(): HasMany
    {
        return $this->hasMany(EmployeeNextOfKin::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class)->orderByDesc('start_date');
    }

    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function compensation(): HasMany
    {
        return $this->hasMany(EmployeeCompensation::class)->orderByDesc('effective_from');
    }

    public function recurringItems(): HasMany
    {
        return $this->hasMany(EmployeeRecurringItem::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(PerformanceGoal::class);
    }

    /** The compensation record in force on a given date (defaults to today). */
    public function compensationOn(?Carbon $date = null): ?EmployeeCompensation
    {
        $date ??= Carbon::today();

        return $this->compensation()
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')
            ->first();
    }

    public function currentAssets(): HasMany
    {
        return $this->assetAssignments()->whereNull('returned_at');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function primaryNextOfKin(): HasOne
    {
        return $this->hasOne(EmployeeNextOfKin::class)->where('is_primary', true);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    /** Whole months of continuous service, from hire (or contract start) to termination or now. */
    public function getTenureMonthsAttribute(): ?int
    {
        $start = $this->date_hired ?? $this->contract_start_date;

        if ($start === null) {
            return null;
        }

        $end = $this->termination_date ?? Carbon::today();

        return $start->diffInMonths($end);
    }

    public function isActive(): bool
    {
        return in_array($this->employment_status, [self::STATUS_ACTIVE, self::STATUS_ON_PROBATION, self::STATUS_ON_LEAVE], true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('employment_status', [self::STATUS_TERMINATED]);
    }

    /**
     * Narrows a roster query to what {@see EmployeePolicy::view}
     * would allow this user to open one-by-one. Full HR (`hr.employees.manage`)
     * sees everyone; a line manager sees their reports and their own record;
     * anyone else sees only their own record.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('hr.employees.manage')) {
            return $query;
        }

        $managedDepartmentIds = Department::query()
            ->where('manager_id', $user->id)
            ->orWhere('assistant_manager_id', $user->id)
            ->pluck('id');

        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->orWhereIn('department_id', $managedDepartmentIds)
            ->orWhereHas('user', fn ($u) => $u->where('manager_id', $user->id)));
    }
}
