<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Everyone sees their own submitted tickets; managers see all.
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id || $user->can('tickets.manage');
    }

    public function create(User $user): bool
    {
        return true; // Every employee may submit tickets (PERMISSIONS_MATRIX).
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.manage');
    }

    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.manage');
    }
}
