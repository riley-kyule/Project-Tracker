<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskApprovalRequested extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $requester,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_approval_requested',
            'task_id' => $this->task->id,
            'board_id' => $this->task->board_id,
            'message' => "{$this->requester->name} asked you to review \"{$this->task->title}\"",
        ];
    }
}
