<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskDue extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public bool $overdue,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->overdue ? 'task_overdue' : 'task_due_soon',
            'task_id' => $this->task->id,
            'board_id' => $this->task->board_id,
            'message' => $this->overdue
                ? "\"{$this->task->title}\" is overdue"
                : "\"{$this->task->title}\" is due within 24 hours",
        ];
    }
}
