<?php

namespace App\Services;

use App\Models\Board;
use App\Models\SlaPolicy;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TicketService
{
    /** $requester defaults to the submitter; differs when IT files a ticket on someone else's behalf. */
    public static function submit(User $submitter, array $data, ?User $requester = null): Ticket
    {
        $requester ??= $submitter;

        $ticket = DB::transaction(function () use ($submitter, $requester, $data) {
            $priority = $data['priority'] ?? 'medium';
            $sla = SlaPolicy::forPriority($priority);

            $ticket = Ticket::create([
                ...$data,
                'priority' => $priority,
                'requester_id' => $requester->id,
                'created_by' => $submitter->id,
                'department_id' => $requester->department_id,
                'status' => Ticket::STATUS_NEW,
                'due_at' => $sla ? now()->addMinutes($sla->resolution_minutes) : null,
            ]);

            $ticket->forceFill(['ticket_number' => $ticket->id])->save();

            $ticket->statusHistory()->create([
                'from_status' => null,
                'to_status' => Ticket::STATUS_NEW,
                'changed_by' => $submitter->id,
                'created_at' => now(),
            ]);

            AuditLogger::log($ticket, 'created', [], [
                'title' => $ticket->title,
                'submitted_by' => $submitter->id !== $requester->id ? $submitter->id : null,
            ]);

            return $ticket;
        });

        TicketNotifier::created($ticket);

        return $ticket;
    }

    public static function assign(Ticket $ticket, User $technician, User $actor): Ticket
    {
        DB::transaction(function () use ($ticket, $technician, $actor) {
            $previousAssignee = $ticket->assigned_to;
            $ticket->forceFill([
                'assigned_to' => $technician->id,
                'assigned_at' => $ticket->assigned_at ?? now(),
                'first_responded_at' => $ticket->first_responded_at ?? now(),
            ]);

            if ($ticket->status === Ticket::STATUS_NEW || $ticket->status === Ticket::STATUS_REOPENED) {
                self::recordTransition($ticket, Ticket::STATUS_ASSIGNED, $actor);
            }

            $ticket->save();
            AuditLogger::log($ticket, 'assigned', ['assigned_to' => $previousAssignee], ['assigned_to' => $technician->id]);
        });

        TicketNotifier::assigned($ticket, $actor);

        return $ticket;
    }

    public static function transition(Ticket $ticket, string $to, User $actor, ?string $reason = null): Ticket
    {
        $allowed = Ticket::TRANSITIONS[$ticket->status] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move a ticket from {$ticket->status} to {$to}.",
            ]);
        }

        DB::transaction(function () use ($ticket, $to, $actor, $reason) {
            $from = $ticket->status;
            self::recordTransition($ticket, $to, $actor, $reason);

            if ($to === Ticket::STATUS_CLOSED) {
                $ticket->forceFill(['closed_at' => now()]);
            }

            $ticket->forceFill(['first_responded_at' => $ticket->first_responded_at ?? now()])->save();
            AuditLogger::log($ticket, 'status_changed', ['status' => $from], ['status' => $to, 'reason' => $reason]);
        });

        TicketNotifier::statusChanged($ticket, $actor);

        return $ticket;
    }

    public static function resolve(Ticket $ticket, User $actor, string $method, string $summary, int $timeSpentMinutes): Ticket
    {
        if (! $ticket->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Only open tickets can be resolved.']);
        }

        DB::transaction(function () use ($ticket, $actor, $method, $summary, $timeSpentMinutes) {
            self::recordTransition($ticket, Ticket::STATUS_RESOLVED, $actor);

            $ticket->forceFill([
                'resolved_at' => now(),
                'first_responded_at' => $ticket->first_responded_at ?? now(),
                'resolution_method' => $method,
                'resolution_summary' => $summary,
                'time_spent_minutes' => $timeSpentMinutes,
            ])->save();

            AuditLogger::log($ticket, 'resolved', [], ['resolution_method' => $method]);
        });

        TicketNotifier::statusChanged($ticket, $actor);

        return $ticket;
    }

    public static function reopen(Ticket $ticket, User $actor, ?string $reason = null): Ticket
    {
        if (! in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED], true)) {
            throw ValidationException::withMessages(['status' => 'Only resolved or closed tickets can be reopened.']);
        }

        DB::transaction(function () use ($ticket, $actor, $reason) {
            $from = $ticket->status;
            self::recordTransition($ticket, Ticket::STATUS_REOPENED, $actor, $reason);

            $ticket->forceFill([
                'resolved_at' => null,
                'closed_at' => null,
                'closed_reason' => null,
                'resolution_method' => null,
            ])->save();

            AuditLogger::log(
                $ticket,
                'reopened',
                ['status' => $from],
                ['status' => Ticket::STATUS_REOPENED, 'reason' => $reason],
            );
        });

        TicketNotifier::statusChanged($ticket, $actor);

        return $ticket;
    }

    /** System-triggered: a ticket sat in waiting_user past its SLA's response_gap_minutes with no reply. */
    public static function closeForInactivity(Ticket $ticket): Ticket
    {
        DB::transaction(function () use ($ticket) {
            self::recordTransition(
                $ticket,
                Ticket::STATUS_CLOSED,
                $ticket->assignee,
                'Closed automatically — no reply received in time',
            );

            $ticket->forceFill(['closed_at' => now(), 'closed_reason' => 'inactivity'])->save();

            AuditLogger::log(
                $ticket,
                'closed_for_inactivity',
                ['status' => Ticket::STATUS_WAITING_USER],
                ['status' => Ticket::STATUS_CLOSED],
            );
        });

        TicketNotifier::closedForInactivity($ticket);

        return $ticket;
    }

    /** Lets the requester or IT confirm an inactivity-closed ticket is actually done, skipping the full resolve form. */
    public static function confirmResolvedAfterInactivity(Ticket $ticket, User $actor): Ticket
    {
        if ($ticket->status !== Ticket::STATUS_CLOSED || $ticket->closed_reason !== 'inactivity') {
            throw ValidationException::withMessages([
                'status' => 'Only tickets closed due to inactivity can be confirmed resolved this way.',
            ]);
        }

        DB::transaction(function () use ($ticket, $actor) {
            self::recordTransition($ticket, Ticket::STATUS_RESOLVED, $actor, 'Confirmed resolved after inactivity closure');

            $ticket->forceFill([
                'resolved_at' => now(),
                'resolution_summary' => 'Confirmed resolved after inactivity closure',
                'closed_at' => null,
                'closed_reason' => null,
            ])->save();

            AuditLogger::log($ticket, 'resolved', ['status' => Ticket::STATUS_CLOSED], ['status' => Ticket::STATUS_RESOLVED]);
        });

        TicketNotifier::statusChanged($ticket, $actor);

        return $ticket;
    }

    /** Convert a complex ticket into a planned task, cross-linking both (WORKFLOWS.md). */
    public static function convertToTask(Ticket $ticket, User $actor, Board $board, int $columnId, array $taskData): Task
    {
        return DB::transaction(function () use ($ticket, $actor, $board, $columnId, $taskData) {
            $task = Task::create([
                'title' => $taskData['title'] ?? $ticket->title,
                'description' => "Converted from ticket TK-{$ticket->ticket_number}.\n\n{$ticket->description}",
                'board_id' => $board->id,
                'board_column_id' => $columnId,
                'department_id' => $board->department_id,
                'created_by' => $actor->id,
                'primary_assignee_id' => $taskData['primary_assignee_id'] ?? $ticket->assigned_to,
                'priority' => $ticket->priority,
                'due_at' => $taskData['due_at'] ?? null,
                'position' => (int) Task::query()->where('board_column_id', $columnId)->max('position') + 1,
                'metadata' => ['source_ticket_id' => $ticket->id],
            ]);

            $task->forceFill(['task_number' => $task->id])->save();
            TaskAssigneeSync::syncPrimary($task, null);

            foreach ($ticket->attachments as $original) {
                $newPath = "attachments/tasks/{$task->id}/".basename($original->path);
                Storage::disk($original->disk)->copy($original->path, $newPath);

                $copy = $task->attachments()->create([
                    'uploaded_by' => $original->uploaded_by,
                    'disk' => $original->disk,
                    'path' => $newPath,
                    'original_name' => $original->original_name,
                    'mime_type' => $original->mime_type,
                    'size_bytes' => $original->size_bytes,
                    'checksum' => $original->checksum,
                ]);

                AuditLogger::log($task, 'attachment_copied_from_ticket', [], [
                    'attachment_id' => $copy->id,
                    'source_attachment_id' => $original->id,
                ]);
            }

            $ticket->forceFill(['converted_task_id' => $task->id]);
            self::recordTransition($ticket, Ticket::STATUS_ESCALATED, $actor, 'Converted to task');
            $ticket->save();

            AuditLogger::log($ticket, 'converted_to_task', [], ['task_id' => $task->id]);
            AuditLogger::log($task, 'created_from_ticket', [], ['ticket_id' => $ticket->id]);

            return $task;
        });
    }

    private static function recordTransition(Ticket $ticket, string $to, User $actor, ?string $reason = null): void
    {
        $ticket->statusHistory()->create([
            'from_status' => $ticket->status,
            'to_status' => $to,
            'changed_by' => $actor->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $ticket->status = $to;
    }

}
