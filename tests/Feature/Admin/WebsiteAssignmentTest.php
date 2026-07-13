<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_managers_can_assign_and_remove_members()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $member = User::factory()->create()->assignRole('Marketing');
        $website = Website::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/websites/{$website->id}/assignments", ['user_id' => $member->id, 'team' => 'marketing'])
            ->assertRedirect();

        $this->assertDatabaseHas('website_assignments', [
            'website_id' => $website->id,
            'user_id' => $member->id,
            'team' => 'marketing',
        ]);

        $assignment = WebsiteAssignment::first();

        $this->actingAs($admin)
            ->delete("/admin/website-assignments/{$assignment->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('website_assignments', ['id' => $assignment->id]);
    }

    public function test_non_managers_cannot_assign_members()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $member = User::factory()->create();
        $website = Website::factory()->create();

        $this->actingAs($employee)
            ->post("/admin/websites/{$website->id}/assignments", ['user_id' => $member->id, 'team' => 'marketing'])
            ->assertForbidden();
    }

    public function test_a_team_cannot_exceed_the_five_member_cap()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $website = Website::factory()->create();

        foreach (range(1, 5) as $i) {
            $website->assignments()->create(['user_id' => User::factory()->create()->id, 'team' => 'marketing']);
        }

        $sixth = User::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/websites/{$website->id}/assignments", ['user_id' => $sixth->id, 'team' => 'marketing'])
            ->assertSessionHasErrors('team');

        $this->assertSame(5, $website->assignments()->where('team', 'marketing')->count());
    }
}
