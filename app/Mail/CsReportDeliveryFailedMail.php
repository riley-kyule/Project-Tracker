<?php

namespace App\Mail;

use App\Models\ReportDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/**
 * Customer Service Board Requirements Specification v1.0 §11 — a delivery
 * that still fails after retries is escalated to an administrator rather
 * than silently sitting as a "failed" row. Mirrors
 * App\Mail\SeoReportDeliveryFailedMail.
 */
class CsReportDeliveryFailedMail extends Mailable
{
    use Queueable;

    public function __construct(public ReportDelivery $delivery) {}

    public function build(): self
    {
        $payload = $this->delivery->snapshot?->payload ?? [];

        return $this
            ->subject('Customer Service daily card report failed to deliver — '.($payload['employee_name'] ?? 'unknown employee'))
            ->markdown('mail.cs-report-delivery-failed', [
                'delivery' => $this->delivery,
                'payload' => $payload,
            ]);
    }
}
