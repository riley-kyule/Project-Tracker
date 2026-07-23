<?php

namespace App\Mail;

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class TicketResponseMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public Comment $comment,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("New response on TK-{$this->ticket->ticket_number}: {$this->ticket->title}")
            ->markdown('mail.ticket-response', [
                'ticket' => $this->ticket,
                'comment' => $this->comment,
                'url' => url("/tickets/{$this->ticket->id}"),
            ]);
    }
}
