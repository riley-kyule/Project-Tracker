<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class TicketClosedInactivityMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function build(): self
    {
        return $this
            ->subject("Ticket TK-{$this->ticket->ticket_number} closed — no reply received")
            ->markdown('mail.ticket-closed-inactivity', [
                'ticket' => $this->ticket,
                'url' => url("/tickets/{$this->ticket->id}"),
            ]);
    }
}
