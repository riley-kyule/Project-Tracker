<?php

namespace Tests\Feature\Collaboration;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(string $visibility = Board::VISIBILITY_COMPANY): Task
    {
        $board = Board::factory()->create(['visibility' => $visibility]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        return Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);
    }

    private function pdf(string $name, int $padding = 200): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".str_repeat('0', $padding));
    }

    public function test_users_can_upload_and_download_attachments()
    {
        Storage::fake('local');

        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($user)
            ->post("/tasks/{$task->id}/attachments", [
                'file' => $this->pdf('report.pdf'),
            ])
            ->assertRedirect();

        $attachment = Attachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('report.pdf', $attachment->original_name);
        $this->assertSame('unscanned', $attachment->scan_status);

        $this->actingAs($user)
            ->get("/attachments/{$attachment->id}")
            ->assertOk()
            ->assertDownload('report.pdf');
    }

    public function test_executable_uploads_are_rejected()
    {
        Storage::fake('local');

        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($user)
            ->post("/tasks/{$task->id}/attachments", [
                'file' => UploadedFile::fake()->create('malware.exe', 10),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_renamed_executable_content_is_rejected()
    {
        Storage::fake('local');

        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();
        $file = UploadedFile::fake()->createWithContent('malware.pdf', "#!/bin/sh\necho compromised");

        $this->actingAs($user)
            ->post("/tasks/{$task->id}/attachments", ['file' => $file])
            ->assertStatus(422);

        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_downloads_respect_board_visibility()
    {
        Storage::fake('local');

        $member = User::factory()->create()->assignRole('Employee');
        $outsider = User::factory()->create()->assignRole('Employee');

        $task = $this->makeTask(Board::VISIBILITY_RESTRICTED);
        $task->board->members()->attach($member->id);

        $this->actingAs($member)->post("/tasks/{$task->id}/attachments", [
            'file' => $this->pdf('secret.pdf'),
        ]);

        $attachment = Attachment::query()->firstOrFail();

        $this->actingAs($outsider)->get("/attachments/{$attachment->id}")->assertForbidden();
        $this->actingAs($member)->get("/attachments/{$attachment->id}")->assertOk();
    }

    public function test_only_uploader_or_admin_can_delete()
    {
        Storage::fake('local');

        $uploader = User::factory()->create()->assignRole('Employee');
        $other = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($uploader)->post("/tasks/{$task->id}/attachments", [
            'file' => $this->pdf('doc.pdf'),
        ]);

        $attachment = Attachment::query()->firstOrFail();

        $this->actingAs($other)->delete("/attachments/{$attachment->id}")->assertForbidden();
        $this->actingAs($uploader)->delete("/attachments/{$attachment->id}")->assertRedirect();

        $this->assertSame(0, Attachment::query()->count());
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_uploader_cannot_delete_after_losing_parent_access()
    {
        Storage::fake('local');

        $uploader = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask(Board::VISIBILITY_RESTRICTED);
        $task->board->members()->attach($uploader->id);

        $this->actingAs($uploader)->post("/tasks/{$task->id}/attachments", [
            'file' => $this->pdf('doc.pdf'),
        ]);
        $attachment = Attachment::query()->firstOrFail();
        $task->board->members()->detach($uploader->id);

        $this->actingAs($uploader)->delete("/attachments/{$attachment->id}")->assertForbidden();
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }
}
