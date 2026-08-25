<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueHealthTest extends TestCase
{
    use RefreshDatabase;

    /** CEO holds every permission Administrator holds (see RoleSeeder.php / PERMISSIONS_MATRIX.md, reversed 2026-08-25). */
    public function test_only_ceo_and_administrators_can_view_queue_health()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $ceo = User::factory()->create()->assignRole('CEO');
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($employee)->get('/admin/queue-health')->assertForbidden();
        $this->actingAs($ceo)->get('/admin/queue-health')->assertOk();
        $this->actingAs($admin)->get('/admin/queue-health')->assertOk();
    }

    public function test_it_lists_failed_jobs_and_pending_counts_by_queue()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Something went wrong in a job handler',
            'failed_at' => now(),
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->subMinutes(5)->timestamp,
            'created_at' => now()->subMinutes(5)->timestamp,
        ]);

        $props = $this->actingAs($admin)->get('/admin/queue-health')->assertOk()->viewData('page')['props'];

        $this->assertSame(1, $props['failedJobsTotal']);
        $this->assertStringContainsString('Something went wrong', $props['failedJobs'][0]['exception']);
        $this->assertSame('default', $props['pendingByQueue'][0]['queue']);
        $this->assertSame(1, $props['pendingByQueue'][0]['total']);
    }
}
