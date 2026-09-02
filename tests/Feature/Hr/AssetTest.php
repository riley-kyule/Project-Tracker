<?php

namespace Tests\Feature\Hr;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    private function hrManager(): User
    {
        return User::factory()->create()->assignRole('HR Manager');
    }

    public function test_hr_staff_can_manage_assets(): void
    {
        $staff = User::factory()->create()->assignRole('HR Staff');

        $this->actingAs($staff)->post('/hr/assets', [
            'asset_tag' => 'AST-9001',
            'name' => 'MacBook Pro',
            'status' => 'in_stock',
            'condition' => 'good',
        ])->assertRedirect();

        $this->assertDatabaseHas('assets', ['asset_tag' => 'AST-9001']);
    }

    public function test_plain_employee_cannot_view_the_asset_register(): void
    {
        $user = User::factory()->create()->assignRole('Employee');

        $this->actingAs($user)->get('/hr/assets')->assertForbidden();
    }

    public function test_assigning_and_returning_an_asset_moves_its_status_and_custodian(): void
    {
        $hr = $this->hrManager();
        $asset = Asset::factory()->create(['status' => 'in_stock']);
        $employee = Employee::factory()->create();

        $this->actingAs($hr)->post("/hr/assets/{$asset->id}/assignments", [
            'employee_id' => $employee->id,
        ])->assertRedirect();

        $asset->refresh();
        $this->assertSame('assigned', $asset->status);
        $this->assertSame($employee->id, $asset->currentAssignment->employee_id);

        $assignment = $asset->currentAssignment;

        $this->actingAs($hr)->patch("/hr/assets/{$asset->id}/assignments/{$assignment->id}", [
            'condition_in' => 'fair',
            'new_status' => 'in_stock',
        ])->assertRedirect();

        $asset->refresh();
        $this->assertSame('in_stock', $asset->status);
        $this->assertSame('fair', $asset->condition);
        $this->assertNull($asset->fresh()->currentAssignment);
    }

    public function test_an_already_assigned_asset_cannot_be_assigned_again(): void
    {
        $hr = $this->hrManager();
        $asset = Asset::factory()->create();
        $one = Employee::factory()->create();
        $two = Employee::factory()->create();

        $this->actingAs($hr)->post("/hr/assets/{$asset->id}/assignments", ['employee_id' => $one->id]);
        $this->actingAs($hr)->post("/hr/assets/{$asset->id}/assignments", ['employee_id' => $two->id])
            ->assertSessionHasErrors('employee_id');
    }
}
