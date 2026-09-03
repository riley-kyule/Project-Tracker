<?php

namespace App\Notifications\Hr;

use App\Console\Commands\SendContractRenewalReminders;
use App\Models\CompanySetting;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Heads-up that an employee's contract is approaching its end date, sent at
 * fixed milestones (see {@see SendContractRenewalReminders})
 * to the employee, the CEO and the HR Manager.
 * Sender name: "CONTRACT RENEWAL REMINDER - {employee}".
 */
class ContractRenewalReminder extends Notification
{
    use Queueable;

    public function __construct(
        public Employee $employee,
        public int $daysRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$address, $name] = CompanySetting::hrMailFrom("CONTRACT RENEWAL REMINDER - {$this->employee->full_name}");

        $ends = $this->employee->contract_end_date?->toFormattedDateString();

        return (new MailMessage)
            ->from($address, $name)
            ->subject("Contract renewal due: {$this->employee->full_name} ({$this->daysRemaining} days)")
            ->greeting('Contract renewal reminder')
            ->line("{$this->employee->full_name}'s contract ends on {$ends} — {$this->daysRemaining} day(s) away.")
            ->line('Department: '.($this->employee->department?->name ?? '—'))
            ->action('Open employee record', route('hr.employees.show', $this->employee))
            ->line('Renewing the contract from the employee record resets their leave entitlement for the new period.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'hr_contract_renewal_reminder',
            'employee_id' => $this->employee->id,
            'days_remaining' => $this->daysRemaining,
            'message' => "{$this->employee->full_name}'s contract ends in {$this->daysRemaining} day(s)",
        ];
    }
}
