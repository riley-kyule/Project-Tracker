<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCommented extends Notification
{
    use Queueable;

    public function __construct(public Comment $comment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $task = $this->comment->commentable;

        return [
            'type' => 'task_commented',
            'task_id' => $task->id,
            'board_id' => $task->board_id,
            'comment_id' => $this->comment->id,
            'title' => $task->title,
            'message' => "{$this->comment->user->name} commented on \"{$task->title}\"",
        ];
    }
}
