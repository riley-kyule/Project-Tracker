<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

/** Sent synchronously from within GenerateWeeklyReport, which is itself the queued unit of work — see app/Jobs/GenerateWeeklyReport.php. */
class WeeklyPersonalSummaryMail extends Mailable
{
    use Queueable;

    /** @param  Collection<int, string>  $completed  lines describing tasks completed this week */
    public function __construct(
        public User $recipient,
        public int $completedCount,
        public Collection $completed,
        public array $pendingBreakdown,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Your weekly summary — '.now()->format('M j, Y'))
            ->markdown('mail.weekly-personal-summary', [
                'recipient' => $this->recipient,
                'completedCount' => $this->completedCount,
                'completed' => $this->completed,
                'pendingBreakdown' => $this->pendingBreakdown,
            ]);
    }
}
