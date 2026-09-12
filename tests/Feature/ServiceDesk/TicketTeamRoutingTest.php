<?php

namespace Tests\Feature\ServiceDesk;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Notifications\TicketSubmitted;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketTeamRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name = 'Hardware'): TicketCategory
    {
        return TicketCategory::query()->where('name', $name)->firstOrFail();
    }

    private function itTech(): User
    {
        $department = Department::query()->where('slug', 'it')->firstOrFail();

        return User::factory()->create(['department_id' => $department->id])->assignRole('IT Technician');
    }

    private function rndTech(): User
    {
        $department = Department::query()->where('slug', 'research-development')->firstOrFail();

        return User::factory()->create(['department_id' => $department->id])->assignRole('Research & Development');
    }

    public function test_department_seeder_creates_research_and_development_with_the_slug_the_ticket_model_expects()
    {
        $this->seed(DepartmentSeeder::class);

        $department = Department::query()->where('name', 'Research & Development')->first();

        $this->assertNotNull($department);
        $this->assertSame('research-development', $department->slug);
        $this->assertContains($department->slug, Ticket::TEAM_DEPARTMENT_SLUGS[Ticket::TEAM_RND]);
    }

    public function test_a_ticket_can_be_submitted_for_it_rnd_or_both()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->post('/tickets', [
            'title' => 'New chip needs testing',
            'description' => 'Prototype board arrived.',
            'category_id' => $this->category()->id,
            'impact' => 'medium',
            'team' => 'rnd',
        ])->assertRedirect();

        $this->assertSame('rnd', Ticket::query()->latest('id')->first()->team);
    }

    public function test_team_defaults_to_it_when_not_specified()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->post('/tickets', [
            'title' => 'Printer jam',
            'description' => 'Paper stuck again.',
            'category_id' => $this->category()->id,
            'impact' => 'low',
        ])->assertRedirect();

        $this->assertSame('it', Ticket::query()->latest('id')->first()->team);
    }

    public function test_it_technician_only_sees_it_and_both_tickets_in_the_queue()
    {
        $itTicket = Ticket::factory()->create(['team' => 'it', 'category_id' => $this->category()->id]);
        $rndTicket = Ticket::factory()->create(['team' => 'rnd', 'category_id' => $this->category()->id]);
        $bothTicket = Ticket::factory()->create(['team' => 'both', 'category_id' => $this->category()->id]);

        $props = $this->actingAs($this->itTech())->get('/tickets')->assertOk()->viewData('page')['props'];
        $ids = collect($props['tickets']['data'])->pluck('id');

        $this->assertTrue($ids->contains($itTicket->id));
        $this->assertTrue($ids->contains($bothTicket->id));
        $this->assertFalse($ids->contains($rndTicket->id));
    }

    public function test_rnd_member_only_sees_rnd_and_both_tickets_in_the_queue()
    {
        $itTicket = Ticket::factory()->create(['team' => 'it', 'category_id' => $this->category()->id]);
        $rndTicket = Ticket::factory()->create(['team' => 'rnd', 'category_id' => $this->category()->id]);
        $bothTicket = Ticket::factory()->create(['team' => 'both', 'category_id' => $this->category()->id]);

        $props = $this->actingAs($this->rndTech())->get('/tickets')->assertOk()->viewData('page')['props'];
        $ids = collect($props['tickets']['data'])->pluck('id');

        $this->assertTrue($ids->contains($rndTicket->id));
        $this->assertTrue($ids->contains($bothTicket->id));
        $this->assertFalse($ids->contains($itTicket->id));
    }

    public function test_ceo_sees_every_team_unrestricted()
    {
        Ticket::factory()->create(['team' => 'it', 'category_id' => $this->category()->id]);
        Ticket::factory()->create(['team' => 'rnd', 'category_id' => $this->category()->id]);

        $ceo = User::factory()->create()->assignRole('CEO');
        $props = $this->actingAs($ceo)->get('/tickets')->assertOk()->viewData('page')['props'];

        $this->assertSame(2, $props['tickets']['total']);
    }

    public function test_it_technician_cannot_view_an_rnd_only_ticket_directly()
    {
        $ticket = Ticket::factory()->create(['team' => 'rnd', 'category_id' => $this->category()->id]);

        $this->actingAs($this->itTech())->get("/tickets/{$ticket->id}")->assertForbidden();
    }

    public function test_rnd_member_can_view_and_assign_a_both_team_ticket()
    {
        $ticket = Ticket::factory()->create(['team' => 'both', 'category_id' => $this->category()->id]);
        $rnd = $this->rndTech();

        $this->actingAs($rnd)->get("/tickets/{$ticket->id}")->assertOk();

        $this->actingAs($rnd)->post("/tickets/{$ticket->id}/assign", ['assigned_to' => $rnd->id])->assertRedirect();
        $this->assertSame($rnd->id, $ticket->fresh()->assigned_to);
    }

    public function test_assignable_technicians_are_scoped_to_the_tickets_team()
    {
        $itTicket = Ticket::factory()->create(['team' => 'it', 'category_id' => $this->category()->id]);
        $itTechnician = $this->itTech();
        $rndTechnician = $this->rndTech();

        $props = $this->actingAs($itTechnician)->get("/tickets/{$itTicket->id}")->assertOk()->viewData('page')['props'];
        $technicianIds = collect($props['technicians'])->pluck('id');

        $this->assertTrue($technicianIds->contains($itTechnician->id));
        $this->assertFalse($technicianIds->contains($rndTechnician->id));
    }

    public function test_rnd_leadership_is_notified_for_an_rnd_ticket_not_it_leadership()
    {
        Notification::fake();

        $rndDept = Department::query()->where('slug', 'research-development')->firstOrFail();
        $rndManager = User::factory()->create(['department_id' => $rndDept->id]);
        $rndDept->forceFill(['manager_id' => $rndManager->id])->save();

        $itDept = Department::query()->where('slug', 'it')->firstOrFail();
        $itManager = User::factory()->create(['department_id' => $itDept->id]);
        $itDept->forceFill(['manager_id' => $itManager->id])->save();

        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->post('/tickets', [
            'title' => 'Sensor calibration off',
            'description' => 'Readings drifted overnight.',
            'category_id' => $this->category()->id,
            'impact' => 'medium',
            'team' => 'rnd',
        ])->assertRedirect();

        Notification::assertSentTo($rndManager, TicketSubmitted::class);
        Notification::assertNotSentTo($itManager, TicketSubmitted::class);
    }
}
