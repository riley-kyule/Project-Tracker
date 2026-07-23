<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class TicketStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function build(): self
    {
        $status = str_replace('_', ' ', $this->ticket->status);

        return $this
            ->subject("Ticket TK-{$this->ticket->ticket_number} is now {$status}")
            ->markdown('mail.ticket-status-changed', [
                'ticket' => $this->ticket,
                'status' => $status,
                'url' => url("/tickets/{$this->ticket->id}"),
            ]);
    }
}
