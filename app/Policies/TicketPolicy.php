<?php

namespace App\Policies;

use App\Models\Department;
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

    /** IT department members (plus CEO/Administrator as overseers) may raise a ticket on someone else's behalf. */
    public function createForOthers(User $user): bool
    {
        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        $itDepartmentId = Department::query()->where('slug', 'it')->value('id');

        return $itDepartmentId !== null && $user->department_id === $itDepartmentId;
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.manage');
    }

    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.manage');
    }

    /** Administrator only, per explicit instruction — not even CEO. */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('Administrator');
    }
}
