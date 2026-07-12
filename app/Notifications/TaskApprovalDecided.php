<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskApprovalDecided extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $verdict = $this->task->approval_status === Task::APPROVAL_APPROVED ? 'approved' : 'sent back for changes';

        return [
            'type' => 'task_approval_decided',
            'task_id' => $this->task->id,
            'board_id' => $this->task->board_id,
            'message' => "\"{$this->task->title}\" was {$verdict}".($this->task->approval_note ? ": {$this->task->approval_note}" : '.'),
        ];
    }
}
