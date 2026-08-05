<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

/** Sent synchronously from within GenerateWeeklyReport, which is itself the queued unit of work — see app/Jobs/GenerateWeeklyReport.php. */
class CeoWeeklySummaryMail extends Mailable
{
    use Queueable;

    /** @param  Collection<int, array{name: string, completed_count: int, completed: Collection<int, array{label: string, url: string, description: ?string, checklist_progress: ?string}>, pending_breakdown: array<string, int>}>  $departments */
    public function __construct(
        public Collection $departments,
        public int $totalCompleted,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Company weekly summary — '.now()->format('M j, Y'))
            ->markdown('mail.ceo-weekly-summary', [
                'departments' => $this->departments,
                'totalCompleted' => $this->totalCompleted,
            ]);
    }
}
