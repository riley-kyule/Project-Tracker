<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCollaboratorAdded extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $assignmentType,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_collaborator_added',
            'task_id' => $this->task->id,
            'board_id' => $this->task->board_id,
            'message' => "You were added to \"{$this->task->title}\" as a {$this->assignmentType}",
        ];
    }
}
