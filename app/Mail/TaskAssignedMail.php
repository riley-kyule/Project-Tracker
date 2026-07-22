<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class TaskAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $assigner,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("You've been assigned: {$this->task->title}")
            ->markdown('mail.task-assigned', [
                'task' => $this->task,
                'assigner' => $this->assigner,
                'url' => url("/boards/{$this->task->board_id}?task={$this->task->id}"),
            ]);
    }
}
