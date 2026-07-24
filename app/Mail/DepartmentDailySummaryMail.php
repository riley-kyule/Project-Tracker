<?php

namespace App\Mail;

use App\Models\Department;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

class DepartmentDailySummaryMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @param Collection<string, Collection<int, string>> $breakdown assignee name => titles of tasks they completed today */
    public function __construct(
        public Department $department,
        public int $completedToday,
        public int $pending,
        public Collection $breakdown,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("{$this->department->name} daily summary — ".now()->format('M j, Y'))
            ->markdown('mail.department-daily-summary', [
                'department' => $this->department,
                'completedToday' => $this->completedToday,
                'pending' => $this->pending,
                'breakdown' => $this->breakdown,
            ]);
    }
}
