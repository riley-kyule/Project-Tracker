<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Task;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /** Executable formats are rejected outright (SECURITY_OPERATIONS). */
    private const BLOCKED_EXTENSIONS = [
        'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'sh', 'app', 'scr', 'pif', 'jar', 'php', 'phar',
    ];

    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('view', $task);

        $request->validate([
            'file' => ['required', 'file', 'max:25600'], // 25 MB
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        abort_if(in_array($extension, self::BLOCKED_EXTENSIONS, true), 422, 'This file type is not allowed.');

        $path = $file->store('attachments/'.$task->id, 'local');

        $attachment = $task->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);

        AuditLogger::log($task, 'attachment_added', [], ['attachment_id' => $attachment->id, 'name' => $attachment->original_name]);

        return back();
    }

    /** File access inherits the parent task's authorization (PERMISSIONS_MATRIX). */
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $task = $attachment->attachable;

        abort_unless($task instanceof Task, 404);
        Gate::authorize('view', $task);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $task = $attachment->attachable;

        abort_unless($task instanceof Task, 404);
        abort_unless(
            $attachment->uploaded_by === $request->user()->id || $request->user()->hasRole('Administrator'),
            403,
        );

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        AuditLogger::log($task, 'attachment_removed', ['name' => $attachment->original_name], []);

        return back();
    }
}
