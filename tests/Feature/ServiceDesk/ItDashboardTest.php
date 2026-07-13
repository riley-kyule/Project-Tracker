<?php

namespace Tests\Feature\ServiceDesk;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_ticket_managers_can_view_it_dashboard()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $tech = User::factory()->create()->assignRole('IT Technician');

        $this->actingAs($employee)->get('/dashboards/it')->assertForbidden();
        $this->actingAs($tech)->get('/dashboards/it')->assertOk();
    }

    public function test_dashboard_counts_are_correct()
    {
        $tech = User::factory()->create()->assignRole('IT Technician');
        $category = TicketCategory::query()->firstOrFail();

        Ticket::factory()->create(['category_id' => $category->id, 'status' => Ticket::STATUS_NEW]);
        Ticket::factory()->create([
            'category_id' => $category->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
            'assigned_to' => $tech->id,
            'priority' => 'critical',
            'due_at' => now()->subHour(),
        ]);
        Ticket::factory()->create([
            'category_id' => $category->id,
            'status' => Ticket::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_method' => 'remote',
        ]);

        $response = $this->actingAs($tech)->get('/dashboards/it')->assertOk();

        $page = $response->viewData('page')['props'];
        $this->assertSame(1, $page['counts']['new']);
        $this->assertSame(1, $page['counts']['unassigned']);
        $this->assertSame(1, $page['counts']['critical']);
        $this->assertSame(1, $page['counts']['overdue']);
        $this->assertSame(0, $page['counts']['response_breached']);
        $this->assertSame(1, $page['counts']['resolved_today']);
        $this->assertSame(['remote' => 1], (array) $page['resolutionMethods']);
    }

    public function test_dashboard_counts_tickets_past_their_first_response_sla()
    {
        $tech = User::factory()->create()->assignRole('IT Technician');
        $category = TicketCategory::query()->firstOrFail();

        // Critical: 30-minute first-response SLA (ServiceDeskSeeder).
        $breached = Ticket::factory()->create([
            'category_id' => $category->id,
            'status' => Ticket::STATUS_ASSIGNED,
            'assigned_to' => $tech->id,
            'priority' => 'critical',
        ]);
        $breached->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($tech)->get('/dashboards/it')->assertOk();

        $this->assertSame(1, $response->viewData('page')['props']['counts']['response_breached']);
    }
}
