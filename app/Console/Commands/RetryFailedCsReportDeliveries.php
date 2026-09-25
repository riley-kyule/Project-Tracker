<?php

namespace App\Console\Commands;

use App\Mail\CsDailyCardReportMail;
use App\Mail\CsReportDeliveryFailedMail;
use App\Models\ReportDelivery;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Customer Service Board Requirements Specification v1.0 §11 — a failed
 * midnight report delivery must not just sit there: retry it automatically,
 * and once retries are exhausted, alert an administrator. Mirrors
 * App\Console\Commands\RetryFailedSeoReportDeliveries.
 */
class RetryFailedCsReportDeliveries extends Command
{
    private const MAX_ATTEMPTS = 3;

    protected $signature = 'ewms:retry-failed-cs-report-deliveries';

    protected $description = 'Retry failed Customer Service daily card report deliveries, and alert an administrator once retries are exhausted';

    public function handle(): int
    {
        $deliveries = ReportDelivery::query()
            ->where('status', ReportDelivery::STATUS_FAILED)
            ->whereHas('snapshot', fn ($q) => $q->where('report_type', ReportSnapshot::TYPE_CS_DAILY_CARD))
            ->with('snapshot')
            ->get();

        foreach ($deliveries as $delivery) {
            if ($delivery->retry_count >= self::MAX_ATTEMPTS) {
                $this->alertAdministrators($delivery);

                continue;
            }

            $this->retry($delivery);
        }

        return self::SUCCESS;
    }

    private function retry(ReportDelivery $delivery): void
    {
        $snapshot = $delivery->snapshot;
        if ($snapshot === null) {
            return;
        }

        $target = $delivery->recipient ?? $delivery->recipient_email;
        if ($target === null) {
            return;
        }

        try {
            Mail::to($target)->send(new CsDailyCardReportMail($snapshot->payload));

            $delivery->update(['status' => ReportDelivery::STATUS_SENT, 'sent_at' => now()]);
        } catch (Throwable $e) {
            $delivery->update([
                'failed_at' => now(),
                'failure_reason' => Str::limit($e->getMessage(), 500),
                'retry_count' => $delivery->retry_count + 1,
            ]);

            if ($delivery->retry_count >= self::MAX_ATTEMPTS) {
                $this->alertAdministrators($delivery->fresh());
            }
        }
    }

    private function alertAdministrators(ReportDelivery $delivery): void
    {
        if ($delivery->admin_alerted_at !== null) {
            return;
        }

        $administrators = User::role('Administrator')->get();
        if ($administrators->isEmpty()) {
            Log::warning('Customer Service report delivery exhausted retries but no Administrator exists to alert.', ['delivery_id' => $delivery->id]);

            return;
        }

        Mail::to($administrators)->send(new CsReportDeliveryFailedMail($delivery));

        $delivery->update(['admin_alerted_at' => now()]);
    }
}
