<?php

namespace App\Notifications\Hr;

use App\Models\CompanySetting;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requester's line manager and HR when a leave request is filed.
 * Mail goes out under the HR sender identity — {@see CompanySetting::hrMailFrom()}.
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
        [$address, $name] = CompanySetting::hrMailFrom();
        $r = $this->leaveRequest->loadMissing('employee', 'leaveType');

        return (new MailMessage)
            ->from($address, $name)
            ->subject("Leave request: {$r->employee->full_name} — {$r->leaveType->name}")
            ->line("{$r->employee->full_name} has requested {$r->days} day(s) of {$r->leaveType->name}.")
            ->line("Dates: {$r->start_date->toFormattedDateString()} to {$r->end_date->toFormattedDateString()}.")
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
