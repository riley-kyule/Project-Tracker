<?php

namespace Tests\Feature\Hr;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Notifications\Hr\LeaveRequestDecided;
use App\Notifications\Hr\LeaveRequestSubmitted;
use App\Services\Hr\Leave\LeaveCalculator;
use App\Services\Hr\Leave\LeaveEntitlementService;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private function employeeWithLogin(array $attrs = []): array
    {
        $user = User::factory()->create()->assignRole('Employee');
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'contract_start_date' => now()->subMonths(6)->toDateString(),
            'contract_end_date' => now()->addMonths(6)->toDateString(),
            ...$attrs,
        ]);

        return [$user, $employee];
    }

    public function test_working_day_calculation_skips_weekends_and_holidays(): void
    {
        PublicHoliday::create(['name' => 'Test Day', 'date' => '2026-03-04', 'is_recurring' => false]);

        // Mon 2 Mar → Fri 6 Mar 2026, with Wed 4th a holiday = 4 working days.
        $days = app(LeaveCalculator::class)->workingDays(Carbon::parse('2026-03-02'), Carbon::parse('2026-03-06'));

        $this->assertSame(4.0, $days);
    }

    public function test_an_employee_can_submit_a_leave_request_and_notifies_manager_and_hr(): void
    {
        Notification::fake();
        $this->seed(HrSeeder::class);

        $department = Department::factory()->create();
        [$managerUser, $manager] = $this->employeeWithLogin(['department_id' => $department->id]);
        $managerUser->assignRole('Department Manager');
        $hr = User::factory()->create()->assignRole('HR Manager');

        [$user, $employee] = $this->employeeWithLogin(['department_id' => $department->id, 'manager_id' => $manager->id]);

        $type = LeaveType::firstWhere('code', 'ANNUAL');

        $this->actingAs($user)->post('/hr/leave/requests', [
            'leave_type_id' => $type->id,
            'start_date' => now()->addWeek()->next(Carbon::MONDAY)->toDateString(),
            'end_date' => now()->addWeek()->next(Carbon::MONDAY)->addDays(2)->toDateString(),
            'reason' => 'Family trip',
        ])->assertRedirect();

        $request = LeaveRequest::first();
        $this->assertSame('pending', $request->status);
        $this->assertEqualsWithDelta(3.0, (float) $request->days, 0.01);
        $this->assertEqualsWithDelta(3.0, (float) $employee->leaveBalances()->where('leave_type_id', $type->id)->value('pending_days'), 0.01);

        Notification::assertSentTo($managerUser, LeaveRequestSubmitted::class);
        Notification::assertSentTo($hr, LeaveRequestSubmitted::class);
    }

    public function test_same_department_overlap_is_blocked_but_sick_leave_is_exempt(): void
    {
        $this->seed(HrSeeder::class);
        $department = Department::factory()->create();

        [, $colleague] = $this->employeeWithLogin(['department_id' => $department->id]);
        [$user, $employee] = $this->employeeWithLogin(['department_id' => $department->id]);

        $annual = LeaveType::firstWhere('code', 'ANNUAL');
        $sick = LeaveType::firstWhere('code', 'SICK');

        $start = now()->addWeeks(2)->next(Carbon::MONDAY);

        // Colleague already approved for that week.
        LeaveRequest::create([
            'employee_id' => $colleague->id, 'leave_type_id' => $annual->id,
            'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addDays(2)->toDateString(),
            'days' => 3, 'status' => 'approved',
        ]);

        $this->actingAs($user)->post('/hr/leave/requests', [
            'leave_type_id' => $annual->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
        ])->assertSessionHasErrors('start_date');

        // Sick leave bypasses the block.
        $this->actingAs($user)->post('/hr/leave/requests', [
            'leave_type_id' => $sick->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('leave_requests', ['employee_id' => $employee->id, 'leave_type_id' => $sick->id]);
    }

    public function test_a_manager_can_approve_and_the_balance_moves_from_pending_to_taken(): void
    {
        Notification::fake();
        $this->seed(HrSeeder::class);

        $department = Department::factory()->create();
        [$managerUser, $manager] = $this->employeeWithLogin(['department_id' => $department->id]);
        $managerUser->assignRole('Department Manager');
        [$user, $employee] = $this->employeeWithLogin(['department_id' => $department->id, 'manager_id' => $manager->id]);
        $type = LeaveType::firstWhere('code', 'ANNUAL');

        $start = now()->addWeeks(3)->next(Carbon::MONDAY);
        $this->actingAs($user)->post('/hr/leave/requests', [
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDay()->toDateString(),
        ]);
        $request = LeaveRequest::first();

        $this->actingAs($managerUser)->post("/hr/leave/requests/{$request->id}/decision", ['approve' => true])->assertRedirect();

        $balance = $employee->leaveBalances()->where('leave_type_id', $type->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $balance->pending_days, 0.01);
        $this->assertEqualsWithDelta(2.0, (float) $balance->taken_days, 0.01);
        $this->assertSame('approved', $request->fresh()->status);
        Notification::assertSentTo($user, LeaveRequestDecided::class);
    }

    public function test_a_manager_cannot_approve_their_own_request(): void
    {
        $this->seed(HrSeeder::class);
        [$managerUser, $manager] = $this->employeeWithLogin();
        $managerUser->assignRole('Department Manager');
        $type = LeaveType::firstWhere('code', 'ANNUAL');

        $start = now()->addWeeks(2)->next(Carbon::MONDAY);
        $this->actingAs($managerUser)->post('/hr/leave/requests', [
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDay()->toDateString(),
        ]);
        $request = LeaveRequest::first();

        $this->actingAs($managerUser)->post("/hr/leave/requests/{$request->id}/decision", ['approve' => true])->assertForbidden();
    }

    public function test_a_department_head_can_file_a_request_for_a_report_but_not_a_stranger(): void
    {
        $this->seed(HrSeeder::class);
        $headUser = User::factory()->create();
        $department = Department::factory()->create(['manager_id' => $headUser->id]);
        Employee::factory()->create(['user_id' => $headUser->id, 'department_id' => $department->id]);

        [, $report] = $this->employeeWithLogin(['department_id' => $department->id]);
        [, $stranger] = $this->employeeWithLogin();

        $type = LeaveType::firstWhere('code', 'ANNUAL');
        $start = now()->addWeeks(2)->next(Carbon::MONDAY);

        $this->actingAs($headUser)->post('/hr/leave/requests', [
            'employee_id' => $report->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDay()->toDateString(),
        ])->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['employee_id' => $report->id]);

        $this->actingAs($headUser)->post('/hr/leave/requests', [
            'employee_id' => $stranger->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDay()->toDateString(),
        ])->assertForbidden();
    }

    public function test_the_leave_management_page_is_for_leave_viewers_only(): void
    {
        $this->seed(HrSeeder::class);
        [$plainUser] = $this->employeeWithLogin();
        $plainUser->assignRole('Employee');
        $hr = User::factory()->create()->assignRole('HR Manager');

        $this->actingAs($plainUser)->get('/hr/leave')->assertForbidden();
        $this->actingAs($hr)->get('/hr/leave')->assertOk();

        // …but the plain employee still has the self-service page.
        $this->actingAs($plainUser)->get('/hr/me/leave')->assertOk();
    }

    public function test_contract_renewal_resets_leave_balances(): void
    {
        $this->seed(HrSeeder::class);
        $hr = User::factory()->create()->assignRole('HR Manager');
        [, $employee] = $this->employeeWithLogin([
            'contract_start_date' => '2025-01-01',
            'contract_end_date' => '2025-12-31',
        ]);
        $type = LeaveType::firstWhere('code', 'ANNUAL');

        app(LeaveEntitlementService::class)->provisionForCurrentPeriod($employee);
        $employee->leaveBalances()->where('leave_type_id', $type->id)->update(['taken_days' => 10]);

        $this->actingAs($hr)->post("/hr/employees/{$employee->id}/renew-contract", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ])->assertRedirect();

        $newBalance = $employee->leaveBalances()
            ->where('leave_type_id', $type->id)
            ->whereDate('period_start', '2026-01-01')
            ->first();

        $this->assertNotNull($newBalance);
        $this->assertEqualsWithDelta(0.0, (float) $newBalance->taken_days, 0.01);
        $this->assertEqualsWithDelta(21.0, (float) $newBalance->entitled_days, 0.01);
    }

    public function test_leave_settings_toggles_are_saved(): void
    {
        $this->seed(HrSeeder::class);
        $hr = User::factory()->create()->assignRole('HR Manager');

        $this->actingAs($hr)->patch('/hr/leave/settings', [
            'entitlement_basis' => 'calendar_year',
            'leave_year_start_month' => 1,
            'default_annual_days' => 25,
            'accrual_enabled' => true,
            'accrual_days_per_month' => 2.0,
            'carryover_enabled' => true,
            'max_carryover_days' => 5,
            'block_same_department_overlap' => false,
            'overlap_exempt_leave_type_codes' => ['SICK'],
            'overlap_override_roles' => ['CEO'],
            'min_notice_days' => 3,
            'require_handover' => true,
        ])->assertRedirect();

        $settings = LeaveSetting::current();
        $this->assertSame('calendar_year', $settings->entitlement_basis);
        $this->assertTrue($settings->accrual_enabled);
        $this->assertFalse($settings->block_same_department_overlap);
        $this->assertSame(25, $settings->default_annual_days);
    }

    public function test_plain_employee_cannot_open_leave_settings(): void
    {
        $user = User::factory()->create()->assignRole('Employee');
        $this->actingAs($user)->get('/hr/leave/settings')->assertForbidden();
    }
}
