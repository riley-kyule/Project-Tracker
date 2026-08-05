<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskRenewed extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_renewed',
            'task_id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'board_id' => $this->task->board_id,
            'title' => $this->task->title,
            'message' => "\"{$this->task->title}\" has renewed and is ready to start again.",
        ];
    }
}
