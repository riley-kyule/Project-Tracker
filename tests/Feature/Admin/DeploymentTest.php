<?php

namespace Tests\Feature\Admin;

use App\Jobs\DeployLatestRelease;
use App\Models\Deployment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/admin/deployments/check')->assertRedirect('/login');
        $this->post('/admin/deployments')->assertRedirect('/login');
    }

    public function test_employees_cannot_check_or_trigger_deployments()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/admin/deployments/check')->assertForbidden();
        $this->actingAs($employee)->post('/admin/deployments')->assertForbidden();
    }

    public function test_administrators_can_check_for_updates()
    {
        Process::fake([
            '*rev-parse*origin/main*' => Process::result('def5678'),
            '*rev-parse*HEAD*' => Process::result('abc1234'),
            '*rev-list*--count*' => Process::result('2'),
            '*log*--oneline*' => Process::result("def5678 Fix bug\nabc9999 Add feature"),
        ]);

        $admin = User::factory()->create()->assignRole('Administrator');

        $response = $this->actingAs($admin)->getJson('/admin/deployments/check')->assertOk();

        $response->assertJson([
            'branch' => 'main',
            'current_sha' => 'abc1234',
            'remote_sha' => 'def5678',
            'up_to_date' => false,
            'behind_by' => 2,
        ]);
    }

    public function test_deployment_is_blocked_when_self_update_is_disabled()
    {
        config(['deploy.enabled' => false]);
        Queue::fake();

        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->postJson('/admin/deployments')->assertStatus(423);

        Queue::assertNothingPushed();
    }

    public function test_ceo_can_trigger_a_deployment_when_enabled()
    {
        config(['deploy.enabled' => true]);
        Queue::fake();

        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->postJson('/admin/deployments')->assertCreated();

        $deployment = Deployment::query()->firstOrFail();
        $response->assertJsonPath('deployment.id', $deployment->id);

        $this->assertSame(Deployment::STATUS_PENDING, $deployment->status);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $ceo->id,
            'auditable_type' => Deployment::class,
            'auditable_id' => $deployment->id,
            'event' => 'triggered',
        ]);

        Queue::assertPushed(DeployLatestRelease::class, fn (DeployLatestRelease $job) => $job->deployment->is($deployment));
    }

    public function test_a_second_deployment_cannot_start_while_one_is_in_progress()
    {
        config(['deploy.enabled' => true]);
        Queue::fake();

        $admin = User::factory()->create()->assignRole('Administrator');
        Deployment::create(['actor_id' => $admin->id, 'status' => Deployment::STATUS_RUNNING]);

        $this->actingAs($admin)->postJson('/admin/deployments')->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_deploy_job_runs_the_release_sequence_and_marks_success()
    {
        Process::fake();

        $admin = User::factory()->create()->assignRole('Administrator');
        $deployment = Deployment::create(['actor_id' => $admin->id, 'status' => Deployment::STATUS_PENDING]);

        (new DeployLatestRelease($deployment))->handle();

        $deployment->refresh();

        $this->assertSame(Deployment::STATUS_SUCCEEDED, $deployment->status);
        $this->assertNotNull($deployment->started_at);
        $this->assertNotNull($deployment->finished_at);
        $this->assertStringContainsString('composer install', $deployment->output);

        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'migrate --force'));
    }

    public function test_deploy_job_restarts_app_and_scheduler_when_compose_project_is_configured()
    {
        config(['deploy.compose_project' => 'ewms']);
        Process::fake();

        $admin = User::factory()->create()->assignRole('Administrator');
        $deployment = Deployment::create(['actor_id' => $admin->id, 'status' => Deployment::STATUS_PENDING]);

        (new DeployLatestRelease($deployment))->handle();

        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'docker compose')
            && str_contains(implode(' ', $process->command), '--project-name ewms')
            && str_contains(implode(' ', $process->command), 'restart app scheduler'));
    }

    public function test_deploy_job_skips_restart_when_compose_project_is_not_configured()
    {
        config(['deploy.compose_project' => null]);
        Process::fake();

        $admin = User::factory()->create()->assignRole('Administrator');
        $deployment = Deployment::create(['actor_id' => $admin->id, 'status' => Deployment::STATUS_PENDING]);

        (new DeployLatestRelease($deployment))->handle();

        $deployment->refresh();

        $this->assertStringContainsString('Skipping app/scheduler restart', $deployment->output);
        Process::assertNotRan(fn ($process) => str_contains(implode(' ', $process->command), 'docker compose'));
    }

    public function test_deploy_job_marks_failure_when_a_step_fails()
    {
        Process::fake([
            '*composer*install*' => Process::result(errorOutput: 'composer.json invalid', exitCode: 1),
            '*' => Process::result(),
        ]);

        $admin = User::factory()->create()->assignRole('Administrator');
        $deployment = Deployment::create(['actor_id' => $admin->id, 'status' => Deployment::STATUS_PENDING]);

        (new DeployLatestRelease($deployment))->handle();

        $deployment->refresh();

        $this->assertSame(Deployment::STATUS_FAILED, $deployment->status);
        $this->assertStringContainsString('composer.json invalid', $deployment->output);
    }
}
