<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

/** Sent synchronously from within GenerateDailyReport, which is itself the queued unit of work — see app/Jobs/GenerateDailyReport.php. */
class CeoDailySummaryMail extends Mailable
{
    use Queueable;

    /**
     * @param  Collection<int, array{
     *     name: string,
     *     completed_today: int,
     *     pending: int,
     *     breakdown: Collection<string, Collection<int, string>>,
     *     comments: Collection<string, Collection<int, string>>,
     * }>  $departments
     */
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
