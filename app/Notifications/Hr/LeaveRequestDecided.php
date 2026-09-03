<?php

namespace App\Notifications\Hr;

use App\Models\CompanySetting;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requester once their leave request is approved or rejected.
 * Sender name: "LEAVE APPROVED - {employee}" / "LEAVE REJECTED - {employee}".
 */
class LeaveRequestDecided extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->leaveRequest->loadMissing('leaveType', 'employee');
        $approved = $r->status === LeaveRequest::STATUS_APPROVED;
        [$address, $name] = CompanySetting::hrMailFrom(
            'LEAVE '.($approved ? 'APPROVED' : 'REJECTED')." - {$r->employee->full_name}",
        );

        return (new MailMessage)
            ->from($address, $name)
            ->subject('Leave request '.($approved ? 'approved' : 'rejected'))
            ->line("Your {$r->leaveType->name} request for {$r->start_date->toFormattedDateString()}–{$r->end_date->toFormattedDateString()} was ".($approved ? 'approved' : 'rejected').'.')
            ->when($r->decision_note, fn (MailMessage $m) => $m->line("Note: {$r->decision_note}"))
            ->action('View my leave', route('hr.me.leave'));
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->leaveRequest;

        return [
            'type' => 'hr_leave_request_decided',
            'leave_request_id' => $r->id,
            'status' => $r->status,
            'message' => "Your leave request was {$r->status}",
        ];
    }
}
