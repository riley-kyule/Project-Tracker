<?php

namespace App\Services;

use App\Mail\TicketAssignedMail;
use App\Mail\TicketClosedInactivityMail;
use App\Mail\TicketCreatedMail;
use App\Mail\TicketResponseMail;
use App\Mail\TicketStatusChangedMail;
use App\Models\Comment;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketSubmitted;
use App\Notifications\TicketUpdated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Centralizes the Service Desk notification matrix: who gets told what,
 * across database, email, and push, for each stage of a ticket's life.
 * IT leadership = the IT department's manager + assistant manager
 * (Department::manager_id/assistant_manager_id), not the tickets.manage
 * permission — the same distinction used for on-behalf ticket creation.
 */
class TicketNotifier
{
    public static function created(Ticket $ticket): void
    {
        foreach (self::dedupe([$ticket->requester, ...self::itLeadership()]) as $recipient) {
            if (! $recipient->wantsNotification('ticket_submitted')) {
                continue;
            }

            $recipient->notify(new TicketSubmitted($ticket));
            Mail::to($recipient)->queue(new TicketCreatedMail($ticket));
            app(PushNotifier::class)->notify($recipient, 'ticket_submitted', [
                'ticket_id' => $ticket->id,
                'title' => "Ticket TK-{$ticket->ticket_number} received",
                'url' => "/tickets/{$ticket->id}",
            ]);
        }
    }

    public static function assigned(Ticket $ticket, User $assigner): void
    {
        foreach (self::dedupe([$ticket->assignee, ...self::itLeadership()], except: $assigner) as $recipient) {
            if (! $recipient->wantsNotification('ticket_assigned')) {
                continue;
            }

            $recipient->notify(new TicketAssigned($ticket, $assigner));
            Mail::to($recipient)->queue(new TicketAssignedMail($ticket, $assigner));
            app(PushNotifier::class)->notify($recipient, 'ticket_assigned', [
                'ticket_id' => $ticket->id,
                'title' => "Ticket TK-{$ticket->ticket_number} assigned",
                'url' => "/tickets/{$ticket->id}",
            ]);
        }
    }

    public static function statusChanged(Ticket $ticket, User $actor): void
    {
        foreach (self::dedupe([$ticket->requester, $ticket->assignee, ...self::itLeadership()], except: $actor) as $recipient) {
            if (! $recipient->wantsNotification('ticket_updated')) {
                continue;
            }

            $recipient->notify(new TicketUpdated($ticket));
            Mail::to($recipient)->queue(new TicketStatusChangedMail($ticket));
            app(PushNotifier::class)->notify($recipient, 'ticket_updated', [
                'ticket_id' => $ticket->id,
                'title' => "Ticket TK-{$ticket->ticket_number} updated",
                'url' => "/tickets/{$ticket->id}",
            ]);
        }
    }

    /** Response emails stay scoped to requester <-> assignee, not IT leadership, to avoid noise on every message. */
    public static function responded(Ticket $ticket, Comment $comment, User $actor): void
    {
        $other = $actor->id === $ticket->requester_id ? $ticket->assignee : $ticket->requester;

        if (! $other || $other->id === $actor->id || ! $other->isActive() || ! $other->wantsNotification('ticket_response')) {
            return;
        }

        Mail::to($other)->queue(new TicketResponseMail($ticket, $comment));
        app(PushNotifier::class)->notify($other, 'ticket_response', [
            'ticket_id' => $ticket->id,
            'title' => "New response on TK-{$ticket->ticket_number}",
            'url' => "/tickets/{$ticket->id}",
        ]);
    }

    public static function closedForInactivity(Ticket $ticket): void
    {
        foreach (self::dedupe([$ticket->requester, $ticket->assignee, ...self::itLeadership()]) as $recipient) {
            if (! $recipient->wantsNotification('ticket_closed_inactivity')) {
                continue;
            }

            $recipient->notify(new TicketUpdated($ticket));
            Mail::to($recipient)->queue(new TicketClosedInactivityMail($ticket));
            app(PushNotifier::class)->notify($recipient, 'ticket_closed_inactivity', [
                'ticket_id' => $ticket->id,
                'title' => "Ticket TK-{$ticket->ticket_number} closed — no reply received",
                'url' => "/tickets/{$ticket->id}",
            ]);
        }
    }

    /** @return Collection<int, User> */
    private static function itLeadership(): Collection
    {
        $department = Department::query()->where('slug', 'it')->first();

        if (! $department) {
            return collect();
        }

        return collect([$department->manager, $department->assistantManager])->filter();
    }

    /**
     * @param  array<int, User|null>  $candidates
     * @return Collection<int, User>
     */
    private static function dedupe(array $candidates, ?User $except = null): Collection
    {
        return collect($candidates)
            ->filter()
            ->filter(fn (User $user) => $user->isActive())
            ->when($except, fn (Collection $users) => $users->reject(fn (User $user) => $user->id === $except->id))
            ->unique('id')
            ->values();
    }
}
