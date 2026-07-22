<?php

namespace App\Mail;

use App\Models\Department;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class DepartmentDailySummaryMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Department $department,
        public int $completedToday,
        public int $pending,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("{$this->department->name} daily summary — ".now()->format('M j, Y'))
            ->markdown('mail.department-daily-summary', [
                'department' => $this->department,
                'completedToday' => $this->completedToday,
                'pending' => $this->pending,
            ]);
    }
}
