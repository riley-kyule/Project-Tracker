<?php

namespace Tests\Feature\Hr;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\Hr\ContractRenewalReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewing_a_contract_rolls_dates_forward_and_records_history(): void
    {
        $hr = User::factory()->create()->assignRole('HR Manager');
        $employee = Employee::factory()->create([
            'job_title' => 'Editor',
            'contract_start_date' => '2025-01-01',
            'contract_end_date' => '2025-12-31',
            'employment_status' => Employee::STATUS_TERMINATED,
        ]);
        $employee->contracts()->create(['title' => 'Editor', 'start_date' => '2025-01-01', 'end_date' => null, 'reason' => 'hire']);

        $this->actingAs($hr)->post("/hr/employees/{$employee->id}/renew-contract", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ])->assertRedirect();

        $employee->refresh();
        $this->assertSame('2026-01-01', $employee->contract_start_date->toDateString());
        $this->assertSame('2026-12-31', $employee->contract_end_date->toDateString());
        $this->assertSame(Employee::STATUS_ACTIVE, $employee->employment_status);

        // Old contract closed, new renewal row opened.
        $this->assertSame('2025-12-31', $employee->contracts()->where('reason', 'hire')->value('end_date')->toDateString());
        $this->assertDatabaseHas('employee_contracts', [
            'employee_id' => $employee->id,
            'reason' => 'renewal',
            'title' => 'Editor',
            'start_date' => '2026-01-01 00:00:00',
        ]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $employee->getMorphClass(), 'event' => 'contract_renewed']);
    }

    public function test_contract_alerts_notify_employee_ceo_and_hr_manager_at_milestones(): void
    {
        Notification::fake();

        $ceo = User::factory()->create()->assignRole('CEO');
        $hrManager = User::factory()->create()->assignRole('HR Manager');
        User::factory()->create()->assignRole('Employee'); // unrelated, should not be notified

        $account = User::factory()->create();
        Employee::factory()->create([
            'user_id' => $account->id,
            'contract_end_date' => now()->addDays(14)->toDateString(),
        ]);

        // Not at a milestone — should stay silent.
        Employee::factory()->create(['contract_end_date' => now()->addDays(9)->toDateString()]);

        $this->artisan('ewms:hr-contract-alerts')->assertSuccessful();

        Notification::assertSentTo([$ceo, $hrManager, $account], ContractRenewalReminder::class);
        Notification::assertCount(3);
    }
}
