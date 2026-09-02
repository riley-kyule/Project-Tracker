<?php

namespace App\Http\Controllers\Hr\Me;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee self-service. Read-only view of the person's own HR record —
 * anyone with a linked {@see Employee}, no permission required.
 */
class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $employee = $request->user()->employee()
            ->with([
                'department:id,name',
                'manager:id,first_name,middle_name,last_name',
                'nextOfKin',
                'contracts.department:id,name',
                'currentAssets.asset:id,asset_tag,name',
            ])
            ->firstOrFail();

        return Inertia::render('hr/me/profile', [
            'employee' => [
                ...$employee->only([
                    'staff_number', 'first_name', 'middle_name', 'last_name', 'date_of_birth',
                    'gender', 'marital_status', 'national_id_number', 'kra_pin', 'nssf_number',
                    'shif_number', 'insurance_membership_number', 'personal_email', 'phone',
                    'alt_phone', 'postal_address', 'physical_address', 'county', 'job_title',
                    'employment_type', 'date_hired', 'contract_start_date', 'contract_end_date',
                    'employment_status', 'bank_name', 'bank_branch', 'bank_account_number', 'payment_method',
                ]),
                'full_name' => $employee->full_name,
                'tenure_months' => $employee->tenure_months,
                'department' => $employee->department?->only(['id', 'name']),
                'manager' => $employee->manager ? ['name' => $employee->manager->full_name] : null,
                'next_of_kin' => $employee->nextOfKin,
                'contracts' => $employee->contracts->map(fn ($c) => [
                    ...$c->only(['id', 'title', 'employment_type', 'start_date', 'end_date']),
                    'department' => $c->department?->only(['id', 'name']),
                ]),
                'assets' => $employee->currentAssets->map(fn ($a) => [
                    'id' => $a->id,
                    'asset' => $a->asset?->only(['asset_tag', 'name']),
                    'assigned_at' => $a->assigned_at,
                ]),
                'documents' => $employee->documents()->orderByDesc('created_at')->get()->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->original_name,
                    'category' => $d->category,
                    'created_at' => $d->created_at,
                ]),
            ],
        ]);
    }

    public function downloadDocument(Request $request, int $document): StreamedResponse
    {
        $employee = $request->user()->employee()->firstOrFail();
        $attachment = $employee->documents()->findOrFail($document);

        return Storage::disk($attachment->disk)
            ->download($attachment->path, $attachment->original_name);
    }
}
