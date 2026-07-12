<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Task;
use App\Models\Ticket;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /** Executable formats are rejected outright (SECURITY_OPERATIONS). */
    private const BLOCKED_EXTENSIONS = [
        'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'sh', 'app', 'scr', 'pif', 'jar', 'php', 'phar',
    ];

    private const ALLOWED_EXTENSIONS = [
        'csv', 'doc', 'docx', 'gif', 'jpeg', 'jpg', 'json', 'md', 'ods', 'odt',
        'pdf', 'png', 'ppt', 'pptx', 'svg', 'txt', 'webp', 'xls', 'xlsx', 'xml', 'zip',
    ];

    private const MIME_TYPES_BY_EXTENSION = [
        'csv' => ['text/csv', 'text/plain'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'gif' => ['image/gif'],
        'jpeg' => ['image/jpeg'],
        'jpg' => ['image/jpeg'],
        'json' => ['application/json', 'text/plain'],
        'md' => ['text/markdown', 'text/plain'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'svg' => ['image/svg+xml', 'text/xml'],
        'txt' => ['text/plain'],
        'webp' => ['image/webp'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'xml' => ['application/xml', 'text/xml', 'text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('view', $task);

        return $this->attach($request, $task, 'tasks');
    }

    public function storeForTicket(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        return $this->attach($request, $ticket, 'tickets');
    }

    /** File access inherits the parent record's authorization (PERMISSIONS_MATRIX). */
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $this->parentOf($attachment));

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $parent = $this->parentOf($attachment);

        Gate::authorize('view', $parent);

        abort_unless(
            $attachment->uploaded_by === $request->user()->id || $request->user()->hasRole('Administrator'),
            403,
        );

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        AuditLogger::log($parent, 'attachment_removed', ['name' => $attachment->original_name], []);

        return back();
    }

    private function attach(Request $request, Model $parent, string $folder): RedirectResponse
    {
        $request->validate([
            'file' => [
                'required',
                File::types(self::ALLOWED_EXTENSIONS)->max('25mb'),
            ],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        abort_if(
            in_array($extension, self::BLOCKED_EXTENSIONS, true)
                || ! in_array($extension, self::ALLOWED_EXTENSIONS, true),
            422,
            'This file type is not allowed.',
        );

        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        abort_unless(
            in_array($detectedMime, self::MIME_TYPES_BY_EXTENSION[$extension], true),
            422,
            'The file contents do not match its extension.',
        );

        $path = $file->store("attachments/{$folder}/{$parent->getKey()}", 'local');

        $attachment = $parent->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $detectedMime,
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);

        AuditLogger::log($parent, 'attachment_added', [], ['attachment_id' => $attachment->id, 'name' => $attachment->original_name]);

        return back();
    }

    private function parentOf(Attachment $attachment): Task|Ticket
    {
        $parent = $attachment->attachable;

        abort_unless($parent instanceof Task || $parent instanceof Ticket, 404);

        return $parent;
    }
}
