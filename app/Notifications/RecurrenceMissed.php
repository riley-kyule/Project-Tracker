<?php

namespace App\Notifications;

use App\Models\RecurrenceRule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RecurrenceMissed extends Notification
{
    use Queueable;

    public function __construct(public RecurrenceRule $rule) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'recurrence_missed',
            'recurrence_rule_id' => $this->rule->id,
            'task_id' => $this->rule->template_task_id,
            'message' => "The recurring schedule for \"{$this->rule->template->title}\" was generated late. Check the scheduler.",
        ];
    }
}
