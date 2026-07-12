<?php

namespace Tests\Feature\Projects;

use App\Models\Country;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_can_view_but_not_create_projects()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/projects')->assertOk();

        $this->actingAs($employee)
            ->post('/projects', ['name' => 'Rogue project', 'owner_id' => $employee->id, 'status' => 'planned', 'health_status' => 'on_track', 'priority' => 'medium'])
            ->assertForbidden();
    }

    public function test_administrator_can_create_project_with_countries_and_websites()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $owner = User::factory()->create()->assignRole('Employee');
        $country = Country::factory()->create();
        $website = Website::factory()->create();

        $this->actingAs($admin)->post('/projects', [
            'name' => 'Q3 Site Migration',
            'owner_id' => $owner->id,
            'status' => 'planned',
            'health_status' => 'on_track',
            'priority' => 'high',
            'country_ids' => [$country->id],
            'website_ids' => [$website->id],
        ])->assertRedirect();

        $project = Project::query()->where('name', 'Q3 Site Migration')->firstOrFail();
        $this->assertSame($owner->id, $project->owner_id);
        $this->assertTrue($project->countries->contains($country));
        $this->assertTrue($project->websites->contains($website));
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Project::class, 'auditable_id' => $project->id, 'event' => 'created']);
    }

    public function test_department_manager_can_only_update_their_own_department_project()
    {
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $it = Department::query()->where('slug', 'it')->firstOrFail();
        $manager = User::factory()->create(['department_id' => $seo->id])->assignRole('Department Manager');

        $ownProject = Project::factory()->create(['department_id' => $seo->id, 'owner_id' => $manager->id]);
        $otherProject = Project::factory()->create(['department_id' => $it->id]);

        $this->actingAs($manager)
            ->patch("/projects/{$ownProject->id}", ['name' => 'Renamed', 'owner_id' => $manager->id, 'status' => 'active', 'health_status' => 'on_track', 'priority' => 'medium'])
            ->assertRedirect();

        $this->actingAs($manager)
            ->patch("/projects/{$otherProject->id}", ['name' => 'Hijacked', 'owner_id' => $manager->id, 'status' => 'active', 'health_status' => 'on_track', 'priority' => 'medium'])
            ->assertForbidden();
    }

    public function test_project_update_is_audited()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $project = Project::factory()->create();

        $this->actingAs($admin)->patch("/projects/{$project->id}", [
            'name' => $project->name,
            'owner_id' => $project->owner_id,
            'status' => 'active',
            'health_status' => 'at_risk',
            'priority' => 'high',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Project::class,
            'auditable_id' => $project->id,
            'event' => 'updated',
        ]);
    }
}
