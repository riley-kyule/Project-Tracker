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
        // Deliberately role-blind, Viewer included: anyone with a laptop can
        // need IT support, and the service desk is the one place a
        // read-only role should still be able to write. Confirmed as
        // intentional during the 2026-08 audit, not an oversight — see
        // PERMISSIONS_MATRIX.md's "Submit tickets" row.
        return true;
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

    /**
     * Was Administrator-only, explicitly excluding CEO, as a deliberate
     * separation of duties. That policy was reversed 2026-08-25 — CEO now
     * holds every permission Administrator holds — so this follows the same
     * 'tickets.manage' permission as manage()/viewInternalNotes() above.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.manage');
    }
}
