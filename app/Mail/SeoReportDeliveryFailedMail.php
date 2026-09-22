<?php

namespace App\Mail;

use App\Models\ReportDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/**
 * SEO Board Requirements Specification v1.1 §4.2.1 — a delivery that still
 * fails after retries is escalated to an administrator rather than silently
 * sitting as a "failed" row only visible on the report-delivery log page.
 */
class SeoReportDeliveryFailedMail extends Mailable
{
    use Queueable;

    public function __construct(public ReportDelivery $delivery) {}

    public function build(): self
    {
        $payload = $this->delivery->snapshot?->payload ?? [];

        return $this
            ->subject('SEO daily card report failed to deliver — '.($payload['employee_name'] ?? 'unknown employee'))
            ->markdown('mail.seo-report-delivery-failed', [
                'delivery' => $this->delivery,
                'payload' => $payload,
            ]);
    }
}
