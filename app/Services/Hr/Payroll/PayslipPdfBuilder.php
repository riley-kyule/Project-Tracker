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

        $pdf = Pdf::loadView('hr.payslip', [
            'payslip' => $payslip,
            'employee' => $payslip->employee,
            'period' => $payslip->period,
            'company' => CompanySetting::current(),
            'appName' => config('app.name'),
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
}
