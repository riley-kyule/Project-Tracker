<?php

namespace Tests\Feature\Hr;

use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use App\Notifications\Hr\ContractRenewalReminder;
use App\Notifications\Hr\LeaveRequestDecided;
use App\Notifications\Hr\LeaveRequestSubmitted;
use App\Notifications\Hr\PayslipReady;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrEmailSenderNamesTest extends TestCase
{
    use RefreshDatabase;

    private function fromOf($mailMessage): array
    {
        // MailMessage stores from() as [address, name].
        return [$mailMessage->from[0] ?? null, $mailMessage->from[1] ?? null];
    }

    public function test_each_hr_email_uses_a_context_specific_sender_name_and_the_configured_address(): void
    {
        CompanySetting::query()->firstOrCreate(['id' => 1])->update(['mail_from_address' => 'no-reply@exotic.test']);
        $this->seed(HrSeeder::class);

        $employee = Employee::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'contract_end_date' => '2026-12-31']);
        $type = LeaveType::firstWhere('code', 'ANNUAL');

        $pending = LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-04-06', 'end_date' => '2026-04-08', 'days' => 3, 'status' => 'pending',
        ]);
        [$addr, $name] = $this->fromOf((new LeaveRequestSubmitted($pending))->toMail(new User));
        $this->assertSame('no-reply@exotic.test', $addr);
        $this->assertSame('LEAVE REQUEST - Jane Doe', $name);

        $pending->update(['status' => 'approved']);
        [, $name] = $this->fromOf((new LeaveRequestDecided($pending))->toMail(new User));
        $this->assertSame('LEAVE APPROVED - Jane Doe', $name);

        $pending->update(['status' => 'rejected']);
        [, $name] = $this->fromOf((new LeaveRequestDecided($pending))->toMail(new User));
        $this->assertSame('LEAVE REJECTED - Jane Doe', $name);

        [, $name] = $this->fromOf((new ContractRenewalReminder($employee, 14))->toMail(new User));
        $this->assertSame('CONTRACT RENEWAL REMINDER - Jane Doe', $name);

        $period = PayrollPeriod::create([
            'year' => 2026, 'month' => 3, 'label' => 'March 2026',
            'start_date' => '2026-03-01', 'end_date' => '2026-03-31', 'pay_date' => '2026-03-28', 'status' => 'paid',
        ]);
        $payslip = Payslip::create(['payroll_period_id' => $period->id, 'employee_id' => $employee->id, 'currency' => 'KES', 'net_pay' => 100]);
        [, $name] = $this->fromOf((new PayslipReady($payslip))->toMail(new User));
        $this->assertSame('March 2026 PAYSLIP - Jane Doe', $name);
    }
}
