<?php

namespace App\Console\Commands;

use App\Mail\SeoDailyCardReportMail;
use App\Mail\SeoReportDeliveryFailedMail;
use App\Models\ReportDelivery;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * SEO Board Requirements Specification v1.1 §4.2.1 — a failed midnight report
 * delivery must not just sit there: retry it automatically, and once retries
 * are exhausted, alert an administrator instead of letting it go unnoticed on
 * the System Report Log page.
 */
class RetryFailedSeoReportDeliveries extends Command
{
    private const MAX_ATTEMPTS = 3;

    protected $signature = 'ewms:retry-failed-seo-report-deliveries';

    protected $description = 'Retry failed SEO daily card report deliveries, and alert an administrator once retries are exhausted';

    public function handle(): int
    {
        $deliveries = ReportDelivery::query()
            ->where('status', ReportDelivery::STATUS_FAILED)
            ->whereHas('snapshot', fn ($q) => $q->where('report_type', ReportSnapshot::TYPE_SEO_DAILY_CARD))
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
            Mail::to($target)->send(new SeoDailyCardReportMail($snapshot->payload));

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
            Log::warning('SEO report delivery exhausted retries but no Administrator exists to alert.', ['delivery_id' => $delivery->id]);

            return;
        }

        Mail::to($administrators)->send(new SeoReportDeliveryFailedMail($delivery));

        $delivery->update(['admin_alerted_at' => now()]);
    }
}
