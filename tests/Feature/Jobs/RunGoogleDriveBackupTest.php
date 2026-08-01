<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RunGoogleDriveBackup;
use App\Models\BackupRun;
use App\Models\CompanySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RunGoogleDriveBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CompanySetting::current()->update([
            'google_drive_refresh_token' => 'refresh-token',
            'google_drive_access_token' => 'access-token',
            'google_drive_token_expires_at' => now()->addHour(),
            'google_drive_folder_id' => 'folder-1',
            'backup_retention_count' => 2,
        ]);

        // Tests run against sqlite, which has no host/port/username/password
        // keys — pg_dump itself is faked below regardless, this just keeps
        // the job's (production-shaped) config lookup from blowing up first.
        config([
            'database.connections.'.config('database.default').'.host' => 'localhost',
            'database.connections.'.config('database.default').'.port' => 5432,
            'database.connections.'.config('database.default').'.username' => 'test',
            'database.connections.'.config('database.default').'.password' => 'test',
        ]);

        // pg_dump/tar aren't really run in tests — the fake writes a small
        // stub file at the requested output path so the upload step (which
        // reads real file content) has something to read.
        Process::fake(function ($process) {
            $command = $process->command;

            if (is_array($command)) {
                foreach ($command as $arg) {
                    if (str_starts_with($arg, '--file=')) {
                        file_put_contents(substr($arg, 7), 'stub-database-dump');
                    }
                }

                if ($command[0] === 'tar') {
                    file_put_contents($command[2], 'stub-attachments-archive');
                }
            }

            return Process::result('', '', 0);
        });
    }

    public function test_a_successful_backup_uploads_both_files_and_records_success()
    {
        Http::fake([
            'www.googleapis.com/drive/v3/files?*' => Http::response(['files' => []]),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response([], 200, ['Location' => 'https://upload.example.com/s']),
            'upload.example.com/*' => Http::sequence()
                ->push(['id' => 'db-file-id'])
                ->push(['id' => 'attachments-file-id']),
        ]);

        RunGoogleDriveBackup::dispatchSync('daily');

        $run = BackupRun::query()->latest('started_at')->firstOrFail();
        $this->assertSame(BackupRun::STATUS_SUCCEEDED, $run->status);
        $this->assertNotNull($run->database_file);
        $this->assertNotNull($run->attachments_file);
        $this->assertNotNull($run->finished_at);
    }

    public function test_it_deletes_backups_beyond_the_retention_count()
    {
        // The fake "list" response is a static fixture — it can't reflect the
        // files this same run just uploaded, so retention math here is over
        // these 3 pre-existing sets only, keep-newest-1.
        CompanySetting::current()->update(['backup_retention_count' => 1]);

        Http::fake([
            'www.googleapis.com/drive/v3/files?*' => Http::response(['files' => [
                ['id' => 'old-1-db', 'name' => 'ewms-2026-01-01_010000-database.sql.gz', 'createdTime' => '2026-01-01T01:00:00Z'],
                ['id' => 'old-1-att', 'name' => 'ewms-2026-01-01_010000-attachments.tar.gz', 'createdTime' => '2026-01-01T01:00:00Z'],
                ['id' => 'old-2-db', 'name' => 'ewms-2026-02-01_010000-database.sql.gz', 'createdTime' => '2026-02-01T01:00:00Z'],
                ['id' => 'old-2-att', 'name' => 'ewms-2026-02-01_010000-attachments.tar.gz', 'createdTime' => '2026-02-01T01:00:00Z'],
                ['id' => 'recent-db', 'name' => 'ewms-2026-03-01_010000-database.sql.gz', 'createdTime' => '2026-03-01T01:00:00Z'],
                ['id' => 'recent-att', 'name' => 'ewms-2026-03-01_010000-attachments.tar.gz', 'createdTime' => '2026-03-01T01:00:00Z'],
            ]]),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response([], 200, ['Location' => 'https://upload.example.com/s']),
            'upload.example.com/*' => Http::sequence()->push(['id' => 'db-file-id'])->push(['id' => 'attachments-file-id']),
            'www.googleapis.com/drive/v3/files/*' => Http::response([]),
        ]);

        RunGoogleDriveBackup::dispatchSync('daily');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'old-1-db'));
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'old-1-att'));
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'old-2-db'));
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'old-2-att'));
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'recent-db'));
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'recent-att'));
    }

    public function test_a_failed_upload_is_recorded_as_a_failed_run()
    {
        Http::fake([
            'www.googleapis.com/drive/v3/files?*' => Http::response(['files' => []]),
            'www.googleapis.com/upload/drive/v3/files*' => Http::response(['error' => 'nope'], 500),
        ]);

        RunGoogleDriveBackup::dispatchSync('daily');

        $run = BackupRun::query()->latest('started_at')->firstOrFail();
        $this->assertSame(BackupRun::STATUS_FAILED, $run->status);
        $this->assertNotNull($run->error_message);
    }

    public function test_it_fails_gracefully_when_drive_is_not_connected()
    {
        CompanySetting::current()->update(['google_drive_refresh_token' => null]);

        RunGoogleDriveBackup::dispatchSync('daily');

        $run = BackupRun::query()->latest('started_at')->firstOrFail();
        $this->assertSame(BackupRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('not connected', $run->error_message);
    }
}
