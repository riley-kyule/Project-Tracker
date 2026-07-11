<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $assigner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'task_id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'board_id' => $this->task->board_id,
            'title' => $this->task->title,
            'message' => "{$this->assigner->name} assigned you \"{$this->task->title}\"",
        ];
    }
}
