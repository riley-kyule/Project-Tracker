<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

class CeoDailySummaryMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @param Collection<int, array{name: string, completed_today: int, pending: int, breakdown: Collection<string, Collection<int, string>>}> $departments */
    public function __construct(
        public Collection $departments,
        public int $totalCompletedToday,
        public int $totalPending,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Daily summary — '.now()->format('M j, Y'))
            ->markdown('mail.ceo-daily-summary', [
                'departments' => $this->departments,
                'totalCompletedToday' => $this->totalCompletedToday,
                'totalPending' => $this->totalPending,
            ]);
    }
}
