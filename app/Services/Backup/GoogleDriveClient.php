<?php

namespace App\Services\Backup;

use App\Models\CompanySetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A deliberately small, dependency-free Drive client (raw REST calls via
 * Laravel's HTTP client) rather than pulling in google/apiclient — this app
 * only ever needs four operations against files it created itself
 * (drive.file scope: create a folder, upload into it, list it, delete from
 * it), which don't justify that package's size.
 */
class GoogleDriveClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    private const FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    public function connected(): bool
    {
        return filled(CompanySetting::current()->google_drive_refresh_token);
    }

    /** The single folder this app is allowed to see under drive.file scope — created on first use, remembered after that. */
    public function folderId(): string
    {
        $settings = CompanySetting::current();

        if ($settings->google_drive_folder_id) {
            return $settings->google_drive_folder_id;
        }

        $found = $this->http()->get(self::FILES_URL, [
            'q' => "mimeType='application/vnd.google-apps.folder' and name='EWMS Backups' and trashed=false",
            'fields' => 'files(id)',
        ])->throw()->json('files', []);

        $folderId = $found[0]['id'] ?? $this->http()->post(self::FILES_URL, [
            'name' => 'EWMS Backups',
            'mimeType' => 'application/vnd.google-apps.folder',
        ])->throw()->json('id');

        $settings->update(['google_drive_folder_id' => $folderId]);

        return $folderId;
    }

    /**
     * Resumable upload: request a session URI, then PUT the whole file to it
     * in one shot. Using the resumable endpoint (rather than simple/multipart
     * upload) means the session URI itself would support retrying just the
     * PUT on a transient failure, even though this client only attempts it
     * once today.
     */
    public function upload(string $localPath, string $filename, string $folderId): string
    {
        $session = $this->http()->post(self::UPLOAD_URL.'?uploadType=resumable', [
            'name' => $filename,
            'parents' => [$folderId],
        ])->throw();

        $uploadUrl = $session->header('Location');

        if (! $uploadUrl) {
            throw new RuntimeException('Google Drive did not return a resumable upload session.');
        }

        return Http::withBody(file_get_contents($localPath), 'application/gzip')
            ->put($uploadUrl)
            ->throw()
            ->json('id');
    }

    /** @return array<int, array{id: string, name: string, createdTime: string}> newest first */
    public function listBackupFiles(string $folderId): array
    {
        return $this->http()->get(self::FILES_URL, [
            'q' => "'{$folderId}' in parents and trashed=false",
            'orderBy' => 'createdTime desc',
            'fields' => 'files(id,name,createdTime)',
        ])->throw()->json('files', []);
    }

    public function delete(string $fileId): void
    {
        $this->http()->delete(self::FILES_URL.'/'.$fileId)->throw();
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->accessToken());
    }

    private function accessToken(): string
    {
        $settings = CompanySetting::current();

        if (! $settings->google_drive_refresh_token) {
            throw new RuntimeException('Google Drive is not connected.');
        }

        if ($settings->google_drive_access_token && $settings->google_drive_token_expires_at?->isFuture()) {
            return $settings->google_drive_access_token;
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $settings->google_drive_refresh_token,
            'grant_type' => 'refresh_token',
        ])->throw();

        $token = $response->json('access_token');

        $settings->update([
            'google_drive_access_token' => $token,
            'google_drive_token_expires_at' => now()->addSeconds($response->json('expires_in', 3600)),
        ]);

        return $token;
    }
}
