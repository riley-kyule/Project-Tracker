<?php

namespace Tests\Feature\Hr;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillEmployeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_linked_employee_records_and_wires_the_manager_chain(): void
    {
        $boss = User::factory()->create(['name' => 'Grace Njeri', 'status' => User::STATUS_ACTIVE]);
        $report = User::factory()->create(['name' => 'John Otieno Doe', 'status' => User::STATUS_ACTIVE, 'manager_id' => $boss->id]);
        User::factory()->create(['status' => User::STATUS_INACTIVE]); // skipped
        $alreadyLinked = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        Employee::factory()->create(['user_id' => $alreadyLinked->id]);

        $this->artisan('ewms:hr-backfill-employees')->assertSuccessful();

        $this->assertSame(3, Employee::count()); // boss + report + the pre-existing one

        $bossEmployee = Employee::firstWhere('user_id', $boss->id);
        $reportEmployee = Employee::firstWhere('user_id', $report->id);

        $this->assertSame('Grace', $bossEmployee->first_name);
        $this->assertSame('Njeri', $bossEmployee->last_name);
        $this->assertTrue($bossEmployee->is_org_head);
        $this->assertSame('John', $reportEmployee->first_name);
        $this->assertSame($bossEmployee->id, $reportEmployee->manager_id);
        $this->assertFalse($reportEmployee->is_org_head);
    }

    public function test_dry_run_creates_nothing(): void
    {
        User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $this->artisan('ewms:hr-backfill-employees --dry-run')->assertSuccessful();

        $this->assertSame(0, Employee::count());
    }
}
