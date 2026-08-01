<?php

namespace App\Jobs;

use App\Models\BackupRun;
use App\Models\CompanySetting;
use App\Services\Backup\GoogleDriveClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Dumps the database and archives attachments, uploads both to the connected Google Drive folder, then rotates old backups past the configured retention count. */
class RunGoogleDriveBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public string $frequency) {}

    public function handle(GoogleDriveClient $drive): void
    {
        $run = BackupRun::query()->create([
            'frequency' => $this->frequency,
            'status' => BackupRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $timestamp = now()->format('Y-m-d_His');
        $dbFile = "ewms-{$timestamp}-database.sql.gz";
        $attachmentsFile = "ewms-{$timestamp}-attachments.tar.gz";
        $dbPath = storage_path("app/private/backups/{$dbFile}");
        $attachmentsPath = storage_path("app/private/backups/{$attachmentsFile}");

        try {
            if (! $drive->connected()) {
                throw new RuntimeException('Google Drive is not connected.');
            }

            if (! is_dir(dirname($dbPath))) {
                mkdir(dirname($dbPath), 0755, true);
            }

            $this->dumpDatabase($dbPath);
            $this->archiveAttachments($attachmentsPath);

            $folderId = $drive->folderId();
            $drive->upload($dbPath, $dbFile, $folderId);
            $drive->upload($attachmentsPath, $attachmentsFile, $folderId);

            $this->rotateOldBackups($drive, $folderId);

            $run->update([
                'status' => BackupRun::STATUS_SUCCEEDED,
                'finished_at' => now(),
                'database_file' => $dbFile,
                'attachments_file' => $attachmentsFile,
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => BackupRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);

            Log::error('Google Drive backup failed.', ['frequency' => $this->frequency, 'error' => $e->getMessage()]);
        } finally {
            @unlink($dbPath);
            @unlink($attachmentsPath);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('RunGoogleDriveBackup job failed.', ['frequency' => $this->frequency, 'error' => $exception->getMessage()]);
    }

    private function dumpDatabase(string $outputPath): void
    {
        $connection = config('database.connections.'.config('database.default'));

        $result = Process::env(['PGPASSWORD' => $connection['password'] ?? ''])
            ->timeout(1200)
            ->run([
                'pg_dump',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--no-owner',
                '--format=custom',
                '--file='.$outputPath,
                $connection['database'],
            ]);

        if ($result->failed()) {
            throw new RuntimeException('pg_dump failed: '.$result->errorOutput());
        }
    }

    private function archiveAttachments(string $outputPath): void
    {
        $attachmentsDir = storage_path('app/private/attachments');

        if (! is_dir($attachmentsDir)) {
            Process::run(['tar', '-czf', $outputPath, '-T', '/dev/null'])->throw();

            return;
        }

        Process::timeout(1200)
            ->run(['tar', '-czf', $outputPath, '-C', dirname($attachmentsDir), basename($attachmentsDir)])
            ->throw();
    }

    /** Keeps the newest N complete backup sets (a "set" = the database + attachments file pair sharing one timestamp), not just N files. */
    private function rotateOldBackups(GoogleDriveClient $drive, string $folderId): void
    {
        $retention = CompanySetting::current()->backup_retention_count ?: 7;

        $sets = collect($drive->listBackupFiles($folderId))
            ->groupBy(fn (array $file) => (string) str($file['name'])->beforeLast('-database.sql.gz')->beforeLast('-attachments.tar.gz'))
            ->sortByDesc(fn (Collection $set) => $set->first()['createdTime'])
            ->values();

        $sets->skip($retention)->each(fn (Collection $set) => $set->each(fn (array $file) => $drive->delete($file['id'])));
    }
}
