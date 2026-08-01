<?php

namespace Tests\Unit\Services\Backup;

use App\Models\CompanySetting;
use App\Services\Backup\GoogleDriveClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleDriveClientTest extends TestCase
{
    use RefreshDatabase;

    private function connectedSettings(array $overrides = []): CompanySetting
    {
        $settings = CompanySetting::current();
        $settings->update(array_merge([
            'google_drive_refresh_token' => 'refresh-token',
            'google_drive_access_token' => 'still-valid-token',
            'google_drive_token_expires_at' => now()->addHour(),
        ], $overrides));

        return $settings->fresh();
    }

    public function test_connected_reflects_whether_a_refresh_token_is_stored()
    {
        $client = new GoogleDriveClient;
        $this->assertFalse($client->connected());

        $this->connectedSettings();
        $this->assertTrue($client->connected());
    }

    public function test_it_refreshes_an_expired_access_token_before_calling_the_api()
    {
        $this->connectedSettings(['google_drive_token_expires_at' => now()->subMinute()]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'brand-new-token', 'expires_in' => 3600]),
            'www.googleapis.com/drive/v3/files*' => Http::response(['files' => []]),
        ]);

        (new GoogleDriveClient)->listBackupFiles('folder-1');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com'));
        $this->assertSame('brand-new-token', CompanySetting::current()->google_drive_access_token);
    }

    public function test_it_reuses_the_stored_access_token_while_still_valid()
    {
        $this->connectedSettings();

        Http::fake([
            'www.googleapis.com/drive/v3/files*' => Http::response(['files' => []]),
        ]);

        (new GoogleDriveClient)->listBackupFiles('folder-1');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com'));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer still-valid-token'));
    }

    public function test_folder_id_reuses_an_existing_folder_instead_of_creating_a_duplicate()
    {
        $this->connectedSettings();

        Http::fake([
            'www.googleapis.com/drive/v3/files?*' => Http::response(['files' => [['id' => 'existing-folder']]]),
        ]);

        $folderId = (new GoogleDriveClient)->folderId();

        $this->assertSame('existing-folder', $folderId);
        $this->assertSame('existing-folder', CompanySetting::current()->google_drive_folder_id);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_folder_id_creates_a_new_folder_when_none_exists()
    {
        $this->connectedSettings();

        Http::fake([
            'www.googleapis.com/drive/v3/files?*' => Http::response(['files' => []]),
            'www.googleapis.com/drive/v3/files' => Http::response(['id' => 'new-folder']),
        ]);

        $folderId = (new GoogleDriveClient)->folderId();

        $this->assertSame('new-folder', $folderId);
        $this->assertSame('new-folder', CompanySetting::current()->google_drive_folder_id);
    }

    public function test_upload_initiates_a_resumable_session_then_puts_the_file_content()
    {
        $this->connectedSettings();
        $localFile = tempnam(sys_get_temp_dir(), 'backup-test');
        file_put_contents($localFile, 'fake-archive-bytes');

        Http::fake([
            'www.googleapis.com/upload/drive/v3/files*' => Http::response([], 200, ['Location' => 'https://upload.example.com/session-1']),
            'upload.example.com/*' => Http::response(['id' => 'uploaded-file-id']),
        ]);

        $fileId = (new GoogleDriveClient)->upload($localFile, 'backup.tar.gz', 'folder-1');

        $this->assertSame('uploaded-file-id', $fileId);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'upload.example.com')
            && $request->body() === 'fake-archive-bytes');

        unlink($localFile);
    }

    public function test_delete_calls_the_drive_delete_endpoint()
    {
        $this->connectedSettings();

        Http::fake(['www.googleapis.com/drive/v3/files/file-to-delete' => Http::response([])]);

        (new GoogleDriveClient)->delete('file-to-delete');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'files/file-to-delete'));
    }
}
