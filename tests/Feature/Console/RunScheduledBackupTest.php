<?php

namespace Tests\Feature\Console;

use App\Jobs\RunGoogleDriveBackup;
use App\Models\BackupRun;
use App\Models\CompanySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RunScheduledBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CompanySetting::current()->update([
            'timezone' => 'Africa/Nairobi',
            'google_drive_refresh_token' => 'refresh-token',
        ]);
    }

    public function test_nothing_is_dispatched_without_a_configured_frequency()
    {
        Bus::fake();

        $this->artisan('ewms:run-scheduled-backup')->assertSuccessful();

        Bus::assertNotDispatched(RunGoogleDriveBackup::class);
    }

    public function test_nothing_is_dispatched_when_drive_is_not_connected()
    {
        Bus::fake();
        CompanySetting::current()->update([
            'backup_frequency' => 'daily',
            'backup_time' => '02:00',
            'google_drive_refresh_token' => null,
        ]);

        $this->artisan('ewms:run-scheduled-backup')->assertSuccessful();

        Bus::assertNotDispatched(RunGoogleDriveBackup::class);
    }

    public function test_nothing_is_dispatched_before_the_configured_time()
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-08-03 01:00:00', 'Africa/Nairobi'));
        CompanySetting::current()->update(['backup_frequency' => 'daily', 'backup_time' => '02:00']);

        $this->artisan('ewms:run-scheduled-backup')->assertSuccessful();

        Bus::assertNotDispatched(RunGoogleDriveBackup::class);
    }

    public function test_a_daily_backup_dispatches_once_the_time_passes()
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-08-03 02:30:00', 'Africa/Nairobi'));
        CompanySetting::current()->update(['backup_frequency' => 'daily', 'backup_time' => '02:00']);

        $this->artisan('ewms:run-scheduled-backup')->assertSuccessful();

        Bus::assertDispatched(RunGoogleDriveBackup::class, fn ($job) => $job->frequency === 'daily');
    }

    public function test_a_daily_backup_does_not_dispatch_twice_in_the_same_day()
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-08-03 02:30:00', 'Africa/Nairobi'));
        CompanySetting::current()->update(['backup_frequency' => 'daily', 'backup_time' => '02:00']);

        BackupRun::query()->create([
            'frequency' => 'daily',
            'status' => BackupRun::STATUS_SUCCEEDED,
            'started_at' => Carbon::parse('2026-08-03 02:00:00', 'Africa/Nairobi'),
            'finished_at' => Carbon::parse('2026-08-03 02:05:00', 'Africa/Nairobi'),
        ]);

        $this->artisan('ewms:run-scheduled-backup')->assertSuccessful();

        Bus::assertNotDispatched(RunGoogleDriveBackup::class);
    }

    public function test_a_previously_failed_run_does_not_block_a_retry_the_same_day()
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-08-03 02:30:00', 'Africa/Nairobi'));
        CompanySetting::current()->update(['backup_frequency' => 'daily', 'backup_time' => '02:00']);

        BackupRun::query()->create([
            'frequency' => 'daily',
            'status' => BackupRun::STATUS_FAILED,
            'started_at' => Carbon::parse('2026-08-03 02:00:00', 'Africa/Nairobi'),
            'finished_at' => Carbon::parse('2026-08-03 02:01:00', 'Africa/Nairobi'),
            'error_message' => 'pg_dump failed',
        ]);

        $this->artisan('ewms:run-scheduled-backup')->assertSuccessful();

        Bus::assertDispatched(RunGoogleDriveBackup::class);
    }

    public function test_a_weekly_backup_does_not_dispatch_twice_in_the_same_week()
    {
        Bus::fake();
        CompanySetting::current()->update(['backup_frequency' => 'weekly', 'backup_time' => '02:00']);

        $this->travelTo(Carbon::parse('2026-08-03 02:30:00', 'Africa/Nairobi'));
        $this->artisan('ewms:run-scheduled-backup')->assertSuccessful();
        Bus::assertDispatched(RunGoogleDriveBackup::class, 1);

        BackupRun::query()->create([
            'frequency' => 'weekly',
            'status' => BackupRun::STATUS_SUCCEEDED,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->travelTo(Carbon::parse('2026-08-05 02:30:00', 'Africa/Nairobi'));
        $this->artisan('ewms:run-scheduled-backup')->assertSuccessful();

        Bus::assertDispatched(RunGoogleDriveBackup::class, 1);
    }
}
