<?php

namespace App\Jobs;

use App\Models\Payslip;
use App\Services\Hr\Payroll\PayslipPdfBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePayslipPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $payslipId) {}

    public function handle(PayslipPdfBuilder $builder): void
    {
        $payslip = Payslip::find($this->payslipId);

        if ($payslip !== null) {
            $builder->store($payslip);
        }
    }
}
