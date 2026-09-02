<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use App\Services\Hr\Leave\LeaveEntitlementService;
use Illuminate\Console\Command;

class AccrueLeave extends Command
{
    protected $signature = 'ewms:accrue-leave';

    protected $description = 'Monthly leave accrual (only when accrual is enabled in leave settings)';

    public function handle(LeaveEntitlementService $entitlement): int
    {
        $settings = LeaveSetting::current();

        // Always make sure every active employee has balance rows for their
        // current period — cheap and keeps self-service honest.
        Employee::query()->active()->each(fn (Employee $e) => $entitlement->provisionForCurrentPeriod($e));

        if (! $settings->accrual_enabled) {
            $this->info('Accrual disabled — balances provisioned only.');

            return self::SUCCESS;
        }

        $perMonth = (float) $settings->accrual_days_per_month;
        $accrualTypeIds = LeaveType::query()->where('accrual_method', 'monthly_accrual')->pluck('id');
        $topped = 0;

        LeaveBalance::query()
            ->whereIn('leave_type_id', $accrualTypeIds)
            ->where(fn ($q) => $q->whereNull('period_end')->orWhere('period_end', '>=', now()->toDateString()))
            ->each(function (LeaveBalance $balance) use ($perMonth, &$topped) {
                $balance->increment('accrued_days', $perMonth);
                $topped++;
            });

        $this->info("Accrued {$perMonth} day(s) across {$topped} balance(s).");

        return self::SUCCESS;
    }
}
