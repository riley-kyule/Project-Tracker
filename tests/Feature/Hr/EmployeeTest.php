<?php

namespace Tests\Feature\Hr;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function hrManager(): User
    {
        return User::factory()->create()->assignRole('HR Manager');
    }

    public function test_employees_without_hr_permission_cannot_list_people(): void
    {
        $user = User::factory()->create()->assignRole('Employee');

        $this->actingAs($user)->get('/hr/employees')->assertForbidden();
    }

    public function test_hr_manager_can_create_an_employee_record(): void
    {
        $hr = $this->hrManager();
        $department = Department::factory()->create();

        $response = $this->actingAs($hr)->post('/hr/employees', [
            'staff_number' => 'EMP-2001',
            'first_name' => 'Wanjiru',
            'last_name' => 'Kamau',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'payment_method' => 'bank',
            'department_id' => $department->id,
            'is_org_head' => true,
            'kra_pin' => 'A012345678Z',
        ]);

        $employee = Employee::firstWhere('staff_number', 'EMP-2001');
        $this->assertNotNull($employee);
        $response->assertRedirect("/hr/employees/{$employee->id}");
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $employee->getMorphClass(), 'event' => 'created']);
    }

    public function test_create_form_dates_persist_and_round_trip_to_the_edit_form_as_ymd(): void
    {
        $hr = $this->hrManager();
        $department = Department::factory()->create();

        $this->actingAs($hr)->post('/hr/employees', [
            'staff_number' => 'EMP-2020',
            'first_name' => 'Date',
            'last_name' => 'Keeper',
            'employment_type' => 'consultancy',
            'employment_status' => 'active',
            'payment_method' => 'bank',
            'department_id' => $department->id,
            'is_org_head' => true,
            'date_hired' => '2026-02-01',
            'contract_start_date' => '2026-02-01',
            'contract_end_date' => '2026-08-01',
        ])->assertRedirect();

        $employee = Employee::firstWhere('staff_number', 'EMP-2020');
        $this->assertSame('2026-02-01', $employee->date_hired->toDateString());
        $this->assertSame('2026-02-01', $employee->contract_start_date->toDateString());
        $this->assertSame('consultancy', $employee->employment_type);

        // The show page (which the edit form reads from) must serialise dates as
        // plain Y-m-d, or the <input type="date"> fields render blank.
        $this->actingAs($hr)->get("/hr/employees/{$employee->id}")->assertInertia(fn ($page) => $page
            ->where('employee.date_hired', '2026-02-01')
            ->where('employee.contract_start_date', '2026-02-01')
            ->where('employee.contract_end_date', '2026-08-01'));
    }

    public function test_department_and_manager_are_required_unless_org_head(): void
    {
        $hr = $this->hrManager();

        $this->actingAs($hr)->post('/hr/employees', [
            'staff_number' => 'EMP-2010',
            'first_name' => 'No',
            'last_name' => 'Chain',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'payment_method' => 'bank',
        ])->assertSessionHasErrors(['department_id', 'manager_id']);
    }

    public function test_a_non_org_head_employee_can_be_created_with_a_manager(): void
    {
        $hr = $this->hrManager();
        $department = Department::factory()->create();
        $manager = Employee::factory()->create(['department_id' => $department->id]);

        $this->actingAs($hr)->post('/hr/employees', [
            'staff_number' => 'EMP-2011',
            'first_name' => 'Has',
            'last_name' => 'Manager',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'payment_method' => 'bank',
            'department_id' => $department->id,
            'manager_id' => $manager->id,
        ])->assertRedirect();

        $this->assertSame($manager->id, Employee::firstWhere('staff_number', 'EMP-2011')->manager_id);
    }

    public function test_kra_pin_format_is_validated(): void
    {
        $hr = $this->hrManager();

        $this->actingAs($hr)->post('/hr/employees', [
            'staff_number' => 'EMP-2002',
            'first_name' => 'Test',
            'last_name' => 'User',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'payment_method' => 'bank',
            'kra_pin' => 'not-a-pin',
        ])->assertSessionHasErrors('kra_pin');
    }

    public function test_gender_only_accepts_male_or_female(): void
    {
        $hr = $this->hrManager();
        $department = Department::factory()->create();

        $base = [
            'staff_number' => 'EMP-2050',
            'first_name' => 'Gee',
            'last_name' => 'Dee',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'payment_method' => 'bank',
            'department_id' => $department->id,
            'is_org_head' => true,
        ];

        $this->actingAs($hr)->post('/hr/employees', [...$base, 'gender' => 'unspecified'])->assertSessionHasErrors('gender');

        $this->actingAs($hr)->post('/hr/employees', [...$base, 'gender' => 'female'])->assertRedirect();
        $this->assertSame('female', Employee::firstWhere('staff_number', 'EMP-2050')->gender);
    }

    public function test_a_user_can_only_be_linked_to_one_employee(): void
    {
        $hr = $this->hrManager();
        $account = User::factory()->create();
        Employee::factory()->create(['user_id' => $account->id]);

        $this->actingAs($hr)->post('/hr/employees', [
            'staff_number' => 'EMP-2003',
            'first_name' => 'Dup',
            'last_name' => 'Link',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'payment_method' => 'bank',
            'user_id' => $account->id,
        ])->assertSessionHasErrors('user_id');
    }

    public function test_an_existing_employee_can_be_linked_to_a_login_account_from_the_edit_page(): void
    {
        $hr = $this->hrManager();
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['user_id' => null, 'department_id' => $department->id, 'is_org_head' => true]);
        $account = User::factory()->create();

        // The edit form is populated from the show page — it must offer the account.
        $this->actingAs($hr)->get("/hr/employees/{$employee->id}")->assertInertia(fn ($page) => $page
            ->where('linkableUsers', fn ($users) => collect($users)->contains('id', $account->id)));

        $this->actingAs($hr)->patch("/hr/employees/{$employee->id}", [
            ...$employee->only(['staff_number', 'first_name', 'last_name', 'employment_type', 'employment_status', 'payment_method', 'department_id']),
            'is_org_head' => true,
            'user_id' => $account->id,
        ])->assertRedirect();

        $this->assertSame($account->id, $employee->fresh()->user_id);
    }

    public function test_an_employee_can_be_unlinked_from_its_login_account(): void
    {
        $hr = $this->hrManager();
        $department = Department::factory()->create();
        $account = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $account->id, 'department_id' => $department->id, 'is_org_head' => true]);

        $this->actingAs($hr)->patch("/hr/employees/{$employee->id}", [
            ...$employee->only(['staff_number', 'first_name', 'last_name', 'employment_type', 'employment_status', 'payment_method', 'department_id']),
            'is_org_head' => true,
            'user_id' => null,
        ])->assertRedirect();

        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_an_employee_can_view_their_own_linked_record_via_self_service(): void
    {
        $account = User::factory()->create()->assignRole('Employee');
        Employee::factory()->create(['user_id' => $account->id, 'first_name' => 'Selfie', 'last_name' => 'Serve']);

        $this->actingAs($account)->get('/hr/me/profile')->assertOk()->assertSee('Selfie Serve');
    }

    public function test_a_manager_can_view_a_direct_report_but_not_an_unrelated_employee(): void
    {
        $managerAccount = User::factory()->create()->assignRole('Department Manager');
        $reportAccount = User::factory()->create(['manager_id' => $managerAccount->id]);
        $report = Employee::factory()->create(['user_id' => $reportAccount->id]);
        $stranger = Employee::factory()->create();

        $this->actingAs($managerAccount)->get("/hr/employees/{$report->id}")->assertOk();
        $this->actingAs($managerAccount)->get("/hr/employees/{$stranger->id}")->assertForbidden();
    }

    public function test_the_people_roster_is_scoped_to_reports_for_a_department_manager(): void
    {
        $managerAccount = User::factory()->create()->assignRole('Department Manager');
        $department = Department::factory()->create(['manager_id' => $managerAccount->id]);
        Employee::factory()->create(['department_id' => $department->id, 'first_name' => 'Team', 'last_name' => 'Member']);
        Employee::factory()->create(['first_name' => 'Unrelated', 'last_name' => 'Person']);

        $this->actingAs($managerAccount)
            ->get('/hr/employees')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('employees', 1)
                ->where('employees.0.full_name', 'Team Member'));
    }

    public function test_next_of_kin_primary_flag_is_exclusive(): void
    {
        $hr = $this->hrManager();
        $employee = Employee::factory()->create();
        $employee->nextOfKin()->create(['name' => 'First', 'is_primary' => true]);

        $this->actingAs($hr)->post("/hr/employees/{$employee->id}/next-of-kin", [
            'name' => 'Second',
            'is_primary' => true,
        ])->assertRedirect();

        $this->assertSame(1, $employee->nextOfKin()->where('is_primary', true)->count());
        $this->assertSame('Second', $employee->nextOfKin()->where('is_primary', true)->value('name'));
    }
}
