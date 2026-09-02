<?php

namespace App\Jobs;

use App\Models\PayrollPeriod;
use App\Services\AuditLogger;
use App\Services\Hr\Payroll\PayrollRunner;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Builds every payslip for a period, then queues a PDF per payslip and flips
 * the period from `processing` to `review`.
 */
class ProcessPayrollRun implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public PayrollPeriod $period) {}

    public function handle(PayrollRunner $runner): void
    {
        $count = $runner->run($this->period);

        $this->period->update([
            'status' => PayrollPeriod::STATUS_REVIEW,
            'processed_at' => now(),
        ]);

        foreach ($this->period->payslips()->pluck('id') as $payslipId) {
            GeneratePayslipPdf::dispatch($payslipId);
        }

        AuditLogger::log($this->period, 'payroll_processed', [], ['payslips' => $count]);
    }

    public function failed(\Throwable $e): void
    {
        $this->period->update(['status' => PayrollPeriod::STATUS_DRAFT, 'notes' => 'Processing failed: '.$e->getMessage()]);
    }
}
