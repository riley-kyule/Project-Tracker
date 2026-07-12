<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAssigned extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public User $assigner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_assigned',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'message' => "{$this->assigner->name} assigned you ticket TK-{$this->ticket->ticket_number}: \"{$this->ticket->title}\"",
        ];
    }
}
