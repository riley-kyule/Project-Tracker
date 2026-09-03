<?php

namespace App\Notifications\Hr;

use App\Models\CompanySetting;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requester's line manager and HR when a leave request is filed.
 * Sender name: "LEAVE REQUEST - {employee}", From address unchanged.
 */
class LeaveRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->leaveRequest->loadMissing('employee', 'leaveType');
        [$address, $name] = CompanySetting::hrMailFrom("LEAVE REQUEST - {$r->employee->full_name}");

        return (new MailMessage)
            ->from($address, $name)
            ->subject("Leave request: {$r->employee->full_name} — {$r->leaveType->name}")
            ->line("{$r->employee->full_name} has requested {$r->days} day(s) of {$r->leaveType->name}.")
            ->line("Dates: {$r->start_date->format('d/M/Y')} to {$r->end_date->format('d/M/Y')}.")
            ->when($r->is_emergency, fn (MailMessage $m) => $m->line('Flagged as emergency leave.'))
            ->when($r->reason, fn (MailMessage $m) => $m->line("Reason: {$r->reason}"))
            ->action('Review request', route('hr.leave.index'));
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->leaveRequest;

        return [
            'type' => 'hr_leave_request_submitted',
            'leave_request_id' => $r->id,
            'employee_id' => $r->employee_id,
            'message' => "{$r->employee->full_name} requested {$r->days} day(s) of leave",
        ];
    }
}
