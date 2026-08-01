<?php

namespace Tests\Feature\Settings;

use App\Models\BackupRun;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_cannot_view_or_update_integration_settings()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/settings/integrations')->assertForbidden();
        $this->actingAs($employee)->patch('/settings/integrations', ['backup_frequency' => 'daily'])->assertForbidden();
    }

    public function test_administrators_can_configure_the_backup_schedule()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)
            ->patch('/settings/integrations', [
                'backup_frequency' => 'weekly',
                'backup_time' => '02:00',
                'backup_retention_count' => 14,
            ])
            ->assertRedirect();

        $settings = CompanySetting::current();
        $this->assertSame('weekly', $settings->backup_frequency);
        $this->assertSame('02:00', $settings->backup_time);
        $this->assertSame(14, $settings->backup_retention_count);
    }

    public function test_an_invalid_backup_frequency_is_rejected()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)
            ->patch('/settings/integrations', ['backup_frequency' => 'hourly'])
            ->assertSessionHasErrors('backup_frequency');
    }

    public function test_the_page_shows_the_most_recent_backup_run()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        BackupRun::query()->create([
            'frequency' => BackupRun::FREQUENCY_DAILY,
            'status' => BackupRun::STATUS_SUCCEEDED,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour()->addMinutes(5),
        ]);

        $response = $this->actingAs($admin)->get('/settings/integrations')->assertOk();
        $this->assertSame('succeeded', $response->viewData('page')['props']['lastBackupRun']['status']);
    }
}
