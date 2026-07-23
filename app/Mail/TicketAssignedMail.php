<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class TicketAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public User $assigner,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Ticket TK-{$this->ticket->ticket_number} assigned: {$this->ticket->title}")
            ->markdown('mail.ticket-assigned', [
                'ticket' => $this->ticket,
                'assigner' => $this->assigner,
                'url' => url("/tickets/{$this->ticket->id}"),
            ]);
    }
}
