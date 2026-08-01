<?php

namespace App\Console\Commands;

use App\Jobs\RunGoogleDriveBackup;
use App\Models\BackupRun;
use App\Models\CompanySetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunScheduledBackup extends Command
{
    protected $signature = 'ewms:run-scheduled-backup';

    protected $description = 'Dispatch a Google Drive backup once the configured frequency\'s time has passed for the current period';

    public function handle(): int
    {
        $settings = CompanySetting::current();
        $frequency = $settings->backup_frequency;

        if ($frequency === null) {
            $this->info('Backups are not configured.');

            return self::SUCCESS;
        }

        if (! $settings->google_drive_refresh_token) {
            $this->info('Google Drive is not connected.');

            return self::SUCCESS;
        }

        $timezone = $settings->timezone ?: 'Africa/Nairobi';
        $now = Carbon::now($timezone);

        if (! $this->isDue($settings->backup_time, $now)) {
            $this->info('Not yet due.');

            return self::SUCCESS;
        }

        // started_at is stored in UTC — the period boundary must be converted
        // to UTC too before it's used as a query bound value, or comparing it
        // against the local ($timezone) clock directly compares mismatched
        // instants (the exact bug the reporting builders' ->utc() calls
        // exist to avoid).
        $periodStart = (match ($frequency) {
            BackupRun::FREQUENCY_WEEKLY => $now->copy()->startOfWeek(),
            BackupRun::FREQUENCY_MONTHLY => $now->copy()->startOfMonth(),
            default => $now->copy()->startOfDay(),
        })->utc();

        $alreadyRanThisPeriod = BackupRun::query()
            ->where('frequency', $frequency)
            ->where('status', '!=', BackupRun::STATUS_FAILED)
            ->where('started_at', '>=', $periodStart)
            ->exists();

        if ($alreadyRanThisPeriod) {
            $this->info('Already ran for this period.');

            return self::SUCCESS;
        }

        RunGoogleDriveBackup::dispatch($frequency);
        $this->info('Backup dispatched.');

        return self::SUCCESS;
    }

    /**
     * Only the time of day is configurable (daily/weekly/monthly share this
     * check) — combined with the period-based "already ran" guard above,
     * a weekly/monthly backup naturally fires on the first day its period
     * begins and the configured time has passed, and self-corrects if a
     * scheduler outage delays that.
     */
    private function isDue(?string $time, Carbon $now): bool
    {
        if ($time === null) {
            return false;
        }

        return $now->format('H:i') >= substr($time, 0, 5);
    }
}
