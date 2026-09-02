<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee documents (signed contracts, ID copies, certificates, statutory
 * letters). Stored on the polymorphic `attachments` table, filed by
 * `category`. Access rides the {@see EmployeePolicy}.
 */
class EmployeeDocumentController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    private const CATEGORIES = ['contract', 'id_copy', 'kra', 'nssf', 'shif', 'certificate', 'other'];

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate([
            'file' => ['required', File::types(self::ALLOWED_EXTENSIONS)->max('15mb')],
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
        ]);

        $file = $request->file('file');
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $path = $file->store("attachments/hr/employees/{$employee->id}", 'local');

        $document = $employee->documents()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
            'mime_type' => $detectedMime,
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'category' => $validated['category'],
        ]);

        AuditLogger::log($employee, 'document_added', [], ['name' => $document->original_name, 'category' => $document->category]);

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Employee $employee, Attachment $document): StreamedResponse
    {
        Gate::authorize('view', $employee);
        abort_unless($document->attachable_type === $employee->getMorphClass() && $document->attachable_id === $employee->id, 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Employee $employee, Attachment $document): RedirectResponse
    {
        Gate::authorize('update', $employee);
        abort_unless($document->attachable_type === $employee->getMorphClass() && $document->attachable_id === $employee->id, 404);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        AuditLogger::log($employee, 'document_removed', ['name' => $document->original_name], []);

        return back()->with('success', 'Document removed.');
    }
}
