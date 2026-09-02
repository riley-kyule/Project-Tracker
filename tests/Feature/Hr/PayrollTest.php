<?php

namespace Tests\Feature\Hr;

use App\Jobs\ProcessPayrollRun;
use App\Jobs\SendPayslipNotification;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Hr\Payroll\PayrollRunner;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private function payrollManager(): User
    {
        return User::factory()->create()->assignRole('HR Manager');
    }

    private function employeeOnSalary(float $basic, array $attrs = []): Employee
    {
        $employee = Employee::factory()->create($attrs);
        $employee->compensation()->create([
            'effective_from' => '2026-01-01',
            'currency' => 'KES',
            'basic_salary' => $basic,
        ]);

        return $employee;
    }

    public function test_hr_staff_without_payroll_permission_cannot_see_payroll(): void
    {
        $staff = User::factory()->create()->assignRole('HR Staff');
        $this->actingAs($staff)->get('/hr/payroll')->assertForbidden();
    }

    public function test_a_payroll_run_produces_a_payslip_with_correct_statutory_figures(): void
    {
        $this->seed(HrSeeder::class);
        $employee = $this->employeeOnSalary(100000);

        $period = PayrollPeriod::create([
            'year' => 2026, 'month' => 3, 'label' => 'March 2026',
            'start_date' => '2026-03-01', 'end_date' => '2026-03-31', 'pay_date' => '2026-03-28',
            'status' => 'processing',
        ]);

        $count = app(PayrollRunner::class)->run($period);

        $this->assertSame(1, $count);
        $payslip = Payslip::firstWhere('employee_id', $employee->id);

        $this->assertEqualsWithDelta(100000, (float) $payslip->gross_pay, 0.01);
        $this->assertEqualsWithDelta(6000, (float) $payslip->nssf_employee, 0.01);
        $this->assertEqualsWithDelta(2750, (float) $payslip->shif_employee, 0.01);
        $this->assertEqualsWithDelta(1500, (float) $payslip->housing_levy_employee, 0.01);
        $this->assertEqualsWithDelta(89750, (float) $payslip->taxable_pay, 0.01);
        $this->assertEqualsWithDelta(19308.35, (float) $payslip->paye, 0.01);
        // net = gross - (paye + nssf + shif + ahl)
        $this->assertEqualsWithDelta(100000 - (19308.35 + 6000 + 2750 + 1500), (float) $payslip->net_pay, 0.02);
        // employer cost = gross + nssf employer + ahl employer + nita(50)
        $this->assertEqualsWithDelta(100000 + 6000 + 1500 + 50, (float) $payslip->employer_cost, 0.02);
    }

    public function test_a_loan_recurring_deduction_decrements_its_balance(): void
    {
        $this->seed(HrSeeder::class);
        $employee = $this->employeeOnSalary(80000);
        $loan = $employee->recurringItems()->create([
            'kind' => 'deduction', 'name' => 'Salary advance', 'calc_type' => 'fixed',
            'amount' => 10000, 'balance' => 25000, 'is_active' => true,
        ]);

        $period = PayrollPeriod::create([
            'year' => 2026, 'month' => 4, 'label' => 'April 2026',
            'start_date' => '2026-04-01', 'end_date' => '2026-04-30', 'pay_date' => '2026-04-28', 'status' => 'processing',
        ]);
        app(PayrollRunner::class)->run($period);

        $this->assertEqualsWithDelta(15000, (float) $loan->fresh()->balance, 0.01);
    }

    public function test_the_full_workflow_from_create_to_paid(): void
    {
        Queue::fake();
        $this->seed(HrSeeder::class);
        $this->employeeOnSalary(50000, ['user_id' => User::factory()->create()->id]);

        $hr = $this->payrollManager();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($hr)->post('/hr/payroll', ['year' => 2026, 'month' => 5])->assertRedirect();
        $period = PayrollPeriod::firstWhere(['year' => 2026, 'month' => 5]);

        $this->actingAs($hr)->post("/hr/payroll/{$period->id}/process")->assertRedirect();
        Queue::assertPushed(ProcessPayrollRun::class);
        $this->assertSame('processing', $period->fresh()->status);

        // Simulate the queued run.
        app(PayrollRunner::class)->run($period->fresh());
        $period->update(['status' => 'review']);

        // HR Manager cannot approve — that's CEO/Admin only.
        $this->actingAs($hr)->post("/hr/payroll/{$period->id}/approve")->assertForbidden();

        $this->actingAs($ceo)->post("/hr/payroll/{$period->id}/approve")->assertRedirect();
        $this->assertSame('approved', $period->fresh()->status);

        $this->actingAs($ceo)->post("/hr/payroll/{$period->id}/mark-paid")->assertRedirect();
        $this->assertSame('paid', $period->fresh()->status);
        Queue::assertPushed(SendPayslipNotification::class);
    }

    public function test_an_employee_sees_only_their_own_paid_payslips(): void
    {
        $this->seed(HrSeeder::class);
        $account = User::factory()->create()->assignRole('Employee');
        $mine = $this->employeeOnSalary(60000, ['user_id' => $account->id]);
        $other = $this->employeeOnSalary(60000);

        $period = PayrollPeriod::create([
            'year' => 2026, 'month' => 6, 'label' => 'June 2026',
            'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'pay_date' => '2026-06-28', 'status' => 'processing',
        ]);
        app(PayrollRunner::class)->run($period);

        // Not paid yet → nothing shown.
        $this->actingAs($account)->get('/hr/me/payslips')->assertInertia(fn ($p) => $p->has('payslips', 0));

        $period->update(['status' => 'paid']);
        $this->actingAs($account)->get('/hr/me/payslips')->assertInertia(fn ($p) => $p->has('payslips', 1));

        $othersPayslip = Payslip::firstWhere('employee_id', $other->id);
        $this->actingAs($account)->get("/hr/me/payslips/{$othersPayslip->id}/download")->assertForbidden();
    }

    public function test_statutory_report_export_returns_csv(): void
    {
        $this->seed(HrSeeder::class);
        $this->employeeOnSalary(120000, ['kra_pin' => 'A111111111X']);
        $hr = $this->payrollManager();

        $period = PayrollPeriod::create([
            'year' => 2026, 'month' => 7, 'label' => 'July 2026',
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'pay_date' => '2026-07-28', 'status' => 'processing',
        ]);
        app(PayrollRunner::class)->run($period);

        $response = $this->actingAs($hr)->get("/hr/payroll/{$period->id}/export/paye");
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('A111111111X', $response->streamedContent());
    }
}
