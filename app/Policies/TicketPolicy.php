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
        return $ticket->requester_id === $user->id || $this->canManageTicket($user, $ticket);
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
        return $this->canManageTicket($user, $ticket);
    }

    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        return $this->canManageTicket($user, $ticket);
    }

    /**
     * Was Administrator-only, explicitly excluding CEO, as a deliberate
     * separation of duties. That policy was reversed 2026-08-25 — CEO now
     * holds every permission Administrator holds — so this follows the same
     * 'tickets.manage' permission as manage()/viewInternalNotes() above.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $this->canManageTicket($user, $ticket);
    }

    /**
     * tickets.manage alone isn't enough once IT and R&D have separate
     * queues — an IT technician has no business in an R&D-only ticket, and
     * vice versa, a 'both' ticket is fair game for either. CEO/Administrator
     * and anyone holding tickets.manage outside both departments (a general
     * grant made through /admin/permissions) bypass the team check entirely,
     * matching Ticket::servicedBy()'s "no department, no restriction" default
     * — the same shape as TicketController::ticketTeamScopeFor().
     */
    private function canManageTicket(User $user, Ticket $ticket): bool
    {
        if (! $user->can('tickets.manage')) {
            return false;
        }

        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        $slug = $user->department?->slug;

        if (! in_array($slug, ['it', 'research-development'], true)) {
            return true;
        }

        return $ticket->servicedBy($user);
    }
}
