<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskBlocked extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_blocked',
            'task_id' => $this->task->id,
            'board_id' => $this->task->board_id,
            'message' => "\"{$this->task->title}\" was moved to Blocked",
        ];
    }
}
