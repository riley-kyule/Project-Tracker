<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression guard for the 2026-08-25 policy reversal (see PERMISSIONS_MATRIX.md
 * "Administrative separation of duties"): CEO now holds every permission
 * Administrator holds. This locks that parity in place and spot-checks the
 * routes whose authorization was previously hardcoded to Administrator only.
 */
class CeoPermissionParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ceo_and_administrator_hold_identical_permissions()
    {
        $ceo = Role::findByName('CEO')->permissions->pluck('name')->sort()->values();
        $administrator = Role::findByName('Administrator')->permissions->pluck('name')->sort()->values();

        $this->assertNotEmpty($ceo);
        $this->assertSame($administrator->all(), $ceo->all());
    }

    public function test_ceo_can_reach_every_route_previously_hardcoded_to_administrator_only()
    {
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/admin/queue-health')->assertOk();
        $this->actingAs($ceo)->get('/admin/report-deliveries')->assertOk();
        $this->actingAs($ceo)->get('/admin/users')->assertOk();
    }
}
