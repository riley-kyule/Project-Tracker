<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\Ticket;
use App\Notifications\TaskDue;
use App\Notifications\TicketOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendDueNotifications extends Command
{
    protected $signature = 'ewms:send-due-notifications';

    protected $description = 'Notify assignees about tasks due soon or overdue and tickets past SLA';

    /** Runs hourly; the dedup window keeps each reminder to roughly once a day. */
    private const DEDUP_HOURS = 22;

    public function handle(): int
    {
        $sentTasks = 0;
        $sentTickets = 0;

        Task::query()
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addDay())
            ->whereNull('completed_at')
            ->whereNull('archived_at')
            ->whereNotNull('primary_assignee_id')
            ->with('assignee')
            ->each(function (Task $task) use (&$sentTasks) {
                $overdue = $task->due_at->isPast();
                $type = $overdue ? 'task_overdue' : 'task_due_soon';

                if ($this->alreadySent($task->assignee->id, $type, 'task_id', $task->id)) {
                    return;
                }

                $task->assignee->notify(new TaskDue($task, $overdue));
                $sentTasks++;
            });

        Ticket::query()
            ->whereIn('status', Ticket::OPEN_STATUSES)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotNull('assigned_to')
            ->with('assignee')
            ->each(function (Ticket $ticket) use (&$sentTickets) {
                if ($this->alreadySent($ticket->assignee->id, 'ticket_overdue', 'ticket_id', $ticket->id)) {
                    return;
                }

                $ticket->assignee->notify(new TicketOverdue($ticket));
                $sentTickets++;
            });

        $this->info("Sent {$sentTasks} task and {$sentTickets} ticket due notifications.");

        return self::SUCCESS;
    }

    private function alreadySent(int $userId, string $type, string $key, int $id): bool
    {
        return DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('created_at', '>=', now()->subHours(self::DEDUP_HOURS))
            ->where('data->type', $type)
            ->where("data->{$key}", $id)
            ->exists();
    }
}
