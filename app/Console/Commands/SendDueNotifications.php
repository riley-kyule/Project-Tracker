<?php

namespace App\Console\Commands;

use App\Models\SlaPolicy;
use App\Models\Task;
use App\Models\Ticket;
use App\Notifications\TaskDue;
use App\Notifications\TicketOverdue;
use App\Notifications\TicketResponseOverdue;
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
        $sentResponseAlerts = 0;

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

                if ($this->alreadySent($task->assignee->id, $type, 'task_id', $task->id)
                    || ! $task->assignee->wantsNotification($type)) {
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
                if ($this->alreadySent($ticket->assignee->id, 'ticket_overdue', 'ticket_id', $ticket->id)
                    || ! $ticket->assignee->wantsNotification('ticket_overdue')) {
                    return;
                }

                $ticket->assignee->notify(new TicketOverdue($ticket));
                $sentTickets++;
            });

        // First-response SLA is separate from the resolution due_at checked above:
        // a ticket can be well within its resolution window but already past the
        // window a technician was supposed to acknowledge it in.
        $slaPolicies = SlaPolicy::query()->where('is_active', true)->get()->keyBy('priority');

        Ticket::query()
            ->whereIn('status', Ticket::OPEN_STATUSES)
            ->whereNull('first_responded_at')
            ->whereNotNull('assigned_to')
            ->with('assignee')
            ->each(function (Ticket $ticket) use ($slaPolicies, &$sentResponseAlerts) {
                $policy = $slaPolicies->get($ticket->priority);

                if (! $policy || $ticket->created_at->copy()->addMinutes($policy->first_response_minutes)->isFuture()) {
                    return;
                }

                if ($this->alreadySent($ticket->assignee->id, 'ticket_response_overdue', 'ticket_id', $ticket->id)
                    || ! $ticket->assignee->wantsNotification('ticket_response_overdue')) {
                    return;
                }

                $ticket->assignee->notify(new TicketResponseOverdue($ticket));
                $sentResponseAlerts++;
            });

        $this->info("Sent {$sentTasks} task, {$sentTickets} ticket overdue, and {$sentResponseAlerts} ticket response SLA notifications.");

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
