<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\Hr\ContractRenewalReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendContractRenewalReminders extends Command
{
    protected $signature = 'ewms:hr-contract-alerts';

    protected $description = 'Remind the employee, CEO and HR Manager when a contract is nearing its end date';

    /** Reminders fire at these day-counts before the contract end date. */
    private const MILESTONE_DAYS = [30, 14, 7, 1];

    public function handle(): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        $recipientPool = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['CEO', 'HR Manager']))
            ->get();

        foreach (self::MILESTONE_DAYS as $days) {
            $targetDate = $today->copy()->addDays($days)->toDateString();

            Employee::query()
                ->where('employment_status', '!=', Employee::STATUS_TERMINATED)
                ->whereDate('contract_end_date', $targetDate)
                ->with(['user', 'department:id,name'])
                ->each(function (Employee $employee) use ($days, $recipientPool, &$sent) {
                    if ($this->alreadyRemindedToday($employee->id, $days)) {
                        return;
                    }

                    $recipients = $recipientPool->collect();

                    if ($employee->user) {
                        $recipients->push($employee->user);
                    }

                    $recipients = $recipients->unique('id')->values();

                    Notification::send($recipients, new ContractRenewalReminder($employee, $days));
                    $sent += $recipients->count();
                });
        }

        $this->info("Sent {$sent} contract renewal reminder notification(s).");

        return self::SUCCESS;
    }

    private function alreadyRemindedToday(int $employeeId, int $days): bool
    {
        return DB::table('notifications')
            ->where('created_at', '>=', now()->subHours(22))
            ->where('data->type', 'hr_contract_renewal_reminder')
            ->where('data->employee_id', $employeeId)
            ->where('data->days_remaining', $days)
            ->exists();
    }
}
