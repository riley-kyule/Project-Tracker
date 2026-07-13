<?php

namespace Tests\Feature\ServiceDesk;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name = 'Hardware'): TicketCategory
    {
        return TicketCategory::query()->where('name', $name)->firstOrFail();
    }

    public function test_employee_can_submit_a_ticket_with_number_confirmation_and_sla_due()
    {
        Notification::fake();

        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->post('/tickets', [
            'title' => 'Laptop will not boot',
            'description' => 'Black screen since this morning.',
            'category_id' => $this->category()->id,
            'impact' => 'high',
        ])->assertRedirect();

        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame($ticket->id, $ticket->ticket_number);
        $this->assertSame(Ticket::STATUS_NEW, $ticket->status);
        $this->assertSame('medium', $ticket->priority); // Hardware default
        $this->assertNotNull($ticket->due_at); // SLA applied
        $this->assertSame(1, $ticket->statusHistory()->count());

        Notification::assertSentTo($employee, TicketSubmitted::class);
    }

    public function test_requesters_see_only_their_own_tickets()
    {
        $alice = User::factory()->create()->assignRole('Employee');
        $bob = User::factory()->create()->assignRole('Employee');
        $tech = User::factory()->create()->assignRole('IT Technician');

        $ticket = Ticket::factory()->create(['requester_id' => $alice->id, 'category_id' => $this->category()->id]);

        $this->actingAs($bob)->get("/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($tech)->get("/tickets/{$ticket->id}")->assertOk();
    }

    public function test_assignment_requires_technician_and_notifies()
    {
        Notification::fake();

        $tech = User::factory()->create()->assignRole('IT Technician');
        $lead = User::factory()->create()->assignRole('IT Technician');
        $employee = User::factory()->create()->assignRole('Employee');

        $ticket = Ticket::factory()->create(['category_id' => $this->category()->id]);

        // A plain employee is not a valid assignee.
        $this->actingAs($lead)
            ->post("/tickets/{$ticket->id}/assign", ['assigned_to' => $employee->id])
            ->assertStatus(422);

        $this->actingAs($lead)
            ->post("/tickets/{$ticket->id}/assign", ['assigned_to' => $tech->id])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->status);
        $this->assertNotNull($ticket->first_responded_at);
        Notification::assertSentTo($tech, TicketAssigned::class);

        // Employees cannot assign at all.
        $this->actingAs($employee)
            ->post("/tickets/{$ticket->id}/assign", ['assigned_to' => $tech->id])
            ->assertForbidden();
    }

    public function test_inactive_technician_cannot_be_assigned()
    {
        $lead = User::factory()->create()->assignRole('IT Technician');
        $inactive = User::factory()->create(['status' => User::STATUS_INACTIVE])->assignRole('IT Technician');
        $ticket = Ticket::factory()->create(['category_id' => $this->category()->id]);

        $this->actingAs($lead)
            ->post("/tickets/{$ticket->id}/assign", ['assigned_to' => $inactive->id])
            ->assertStatus(422);

        $this->assertNull($ticket->refresh()->assigned_to);
    }

    public function test_invalid_transitions_are_rejected()
    {
        $tech = User::factory()->create()->assignRole('IT Technician');
        $ticket = Ticket::factory()->create(['category_id' => $this->category()->id]);

        $this->actingAs($tech)
            ->from('/tickets')
            ->post("/tickets/{$ticket->id}/transition", ['status' => Ticket::STATUS_CLOSED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Ticket::STATUS_NEW, $ticket->refresh()->status);
    }

    public function test_resolution_requires_method_and_summary_and_records_history()
    {
        $tech = User::factory()->create()->assignRole('IT Technician');
        $ticket = Ticket::factory()->create([
            'category_id' => $this->category()->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($tech)
            ->from('/tickets')
            ->post("/tickets/{$ticket->id}/resolve", ['resolution_method' => 'remote'])
            ->assertSessionHasErrors('resolution_summary');

        $this->actingAs($tech)->post("/tickets/{$ticket->id}/resolve", [
            'resolution_method' => 'remote',
            'resolution_summary' => 'Reinstalled the display driver over remote session.',
            'time_spent_minutes' => 35,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->status);
        $this->assertSame('remote', $ticket->resolution_method);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame(35, $ticket->time_spent_minutes);
        $this->assertTrue($ticket->statusHistory()->where('to_status', Ticket::STATUS_RESOLVED)->exists());
    }

    public function test_requester_can_reopen_resolved_ticket()
    {
        $requester = User::factory()->create()->assignRole('Employee');
        $ticket = Ticket::factory()->create([
            'requester_id' => $requester->id,
            'category_id' => $this->category()->id,
            'status' => Ticket::STATUS_RESOLVED,
            'resolution_method' => 'remote',
            'resolved_at' => now(),
        ]);

        $this->actingAs($requester)
            ->post("/tickets/{$ticket->id}/reopen", ['reason' => 'Problem came back'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_REOPENED, $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->resolution_method);
    }

    public function test_internal_notes_are_hidden_from_requesters()
    {
        $requester = User::factory()->create()->assignRole('Employee');
        $tech = User::factory()->create()->assignRole('IT Technician');
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id, 'category_id' => $this->category()->id]);

        $this->actingAs($tech)->post("/tickets/{$ticket->id}/comments", [
            'body' => 'User has broken this twice before.',
            'is_internal' => true,
        ]);
        $this->actingAs($tech)->post("/tickets/{$ticket->id}/comments", [
            'body' => 'We are looking into it.',
        ]);

        // A requester trying to flag internal is ignored.
        $this->actingAs($requester)->post("/tickets/{$ticket->id}/comments", [
            'body' => 'Any update?',
            'is_internal' => true,
        ]);

        $this->assertSame(1, $ticket->comments()->where('is_internal', true)->count());

        $response = $this->actingAs($requester)->get("/tickets/{$ticket->id}")->assertOk();
        $this->assertStringNotContainsString('broken this twice', $response->getContent());
        $this->assertStringContainsString('We are looking into it.', $response->getContent());
    }
}
