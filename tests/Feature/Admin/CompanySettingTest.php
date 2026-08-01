<?php

namespace Tests\Feature\Admin;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_set_ceo_summary_times()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)
            ->patch('/admin/company-settings', [
                'ceo_summary_time' => '08:00',
                'ceo_weekly_summary_time' => '17:00',
            ])
            ->assertRedirect();

        $setting = CompanySetting::current();
        $this->assertSame('08:00', $setting->ceo_summary_time);
        $this->assertSame('17:00', $setting->ceo_weekly_summary_time);
    }

    public function test_employees_cannot_update_company_settings()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)
            ->patch('/admin/company-settings', ['ceo_weekly_summary_time' => '17:00'])
            ->assertForbidden();
    }
}
