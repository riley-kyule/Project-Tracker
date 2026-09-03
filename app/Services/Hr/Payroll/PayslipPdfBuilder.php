<?php

namespace App\Services\Hr\Payroll;

use App\Models\CompanySetting;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PayslipPdfBuilder
{
    /** Render the payslip to a PDF on the local disk and return its path. */
    public function store(Payslip $payslip): string
    {
        $payslip->loadMissing('employee.department', 'period');
        $company = CompanySetting::current();

        $pdf = Pdf::loadView('hr.payslip', [
            'payslip' => $payslip,
            'employee' => $payslip->employee,
            'period' => $payslip->period,
            'company' => $company,
            'companyName' => $company->payslip_company_name ?: config('app.name'),
            'logoData' => $this->logoDataUri($company->payslip_logo_path),
        ]);

        $path = "hr/payslips/{$payslip->payroll_period_id}/{$payslip->id}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $payslip->update(['pdf_path' => $path]);

        return $path;
    }

    public function stream(Payslip $payslip): Response
    {
        if ($payslip->pdf_path && Storage::disk('local')->exists($payslip->pdf_path)) {
            return Storage::disk('local')->download($payslip->pdf_path, $this->filename($payslip));
        }

        $this->store($payslip);

        return Storage::disk('local')->download($payslip->pdf_path, $this->filename($payslip));
    }

    private function filename(Payslip $payslip): string
    {
        $name = str($payslip->employee->full_name)->slug();

        return "payslip-{$name}-{$payslip->period->year}-{$payslip->period->month}.pdf";
    }

    /** The letterhead logo as a base64 data URI dompdf can embed, or null. */
    private function logoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode(Storage::disk('local')->get($path));
    }
}
