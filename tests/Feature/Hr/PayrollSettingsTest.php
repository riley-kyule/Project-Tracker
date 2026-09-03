<?php

namespace Tests\Feature\Hr;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayrollSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function payrollManager(): User
    {
        return User::factory()->create()->assignRole('HR Manager');
    }

    public function test_hr_staff_without_payroll_permission_cannot_open_settings(): void
    {
        $staff = User::factory()->create()->assignRole('HR Staff');
        $this->actingAs($staff)->get('/hr/payroll/settings')->assertForbidden();
    }

    public function test_payroll_manager_saves_letterhead_and_dispatch_settings(): void
    {
        $hr = $this->payrollManager();

        $this->actingAs($hr)->patch('/hr/payroll/settings', [
            'company_kra_pin' => 'P051234567X',
            'nssf_employer_number' => 'NSSF-99',
            'shif_employer_number' => 'SHIF-99',
            'payroll_currency' => 'KES',
            'default_pay_day' => 25,
            'nita_levy_enabled' => true,
            'payslip_company_name' => 'Acme Ltd',
            'payslip_company_address' => "1 Loita St\nNairobi",
            'payslip_footer_note' => 'Queries: hr@acme.test',
            'payslip_dispatch_timing' => 'on_pay_date',
            'payroll_requires_second_approval' => true,
        ])->assertRedirect();

        $s = CompanySetting::current();
        $this->assertSame('Acme Ltd', $s->payslip_company_name);
        $this->assertSame('on_pay_date', $s->payslip_dispatch_timing);
        $this->assertTrue((bool) $s->payroll_requires_second_approval);
        $this->assertSame(25, (int) $s->default_pay_day);
    }

    public function test_dispatch_timing_only_accepts_known_values(): void
    {
        $hr = $this->payrollManager();

        $this->actingAs($hr)->patch('/hr/payroll/settings', [
            'payroll_currency' => 'KES',
            'default_pay_day' => 28,
            'payslip_dispatch_timing' => 'whenever',
        ])->assertSessionHasErrors('payslip_dispatch_timing');
    }

    public function test_logo_upload_and_removal(): void
    {
        Storage::fake('local');
        $hr = $this->payrollManager();

        $this->actingAs($hr)->post('/hr/payroll/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertRedirect();

        $path = CompanySetting::current()->payslip_logo_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($hr)->delete('/hr/payroll/settings/logo')->assertRedirect();
        $this->assertNull(CompanySetting::current()->fresh()->payslip_logo_path);
        Storage::disk('local')->assertMissing($path);
    }
}
