<?php

namespace App\Mail;

use App\Models\Department;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

/** Sent synchronously from within GenerateDailyReport, which is itself the queued unit of work — see app/Jobs/GenerateDailyReport.php. */
class DepartmentDailySummaryMail extends Mailable
{
    use Queueable;

    /**
     * @param  Collection<string, Collection<int, string>>  $breakdown  assignee name => lines describing tasks they completed today
     * @param  Collection<string, Collection<int, string>>  $comments  task title => "Author: comment" lines posted today
     * @param  array<string, int>  $pendingBreakdown  one of TaskStatusClassifier::ALL => count
     * @param  Collection<string, Collection<int, string>>  $progressNotes  task title => "Author [type]: note" lines posted today
     * @param  Collection<string, Collection<int, string>>  $reopenedToday  assignee name => titles of tasks reopened today
     * @param  array{active_members: int, members_with_activity: int, missing_activity: int, tasks_updated_today: int, overdue: int}  $completeness
     */
    public function __construct(
        public Department $department,
        public int $completedToday,
        public int $pending,
        public Collection $breakdown,
        public Collection $comments,
        public array $pendingBreakdown = [],
        public Collection $progressNotes = new Collection,
        public Collection $reopenedToday = new Collection,
        public array $completeness = [],
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
                'comments' => $this->comments,
                'pendingBreakdown' => $this->pendingBreakdown,
                'progressNotes' => $this->progressNotes,
                'reopenedToday' => $this->reopenedToday,
                'completeness' => $this->completeness,
            ]);
    }
}
