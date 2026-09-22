<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/**
 * The midnight per-employee daily card report — SEO Board Requirements
 * Specification v1.1 §4.2.1. Sent synchronously from within
 * GenerateSeoDailyCardReport, which is itself the queued unit of work — same
 * shape as DepartmentDailySummaryMail/GenerateDailyReport.
 *
 * @param array{
 *     employee_name: string, role: ?string, department: string, work_date: string, card_id: int, closed_at: string,
 *     planned_points: int, employee_submitted_points: ?float, approved_points: ?float, quota_percentage: ?float,
 *     items: list<array{name: string, section: string, weight: float, target_quantity: ?float, achieved_quantity: ?float, employee_status: string, submitted_at: ?string, hod_decision: ?string, evidence_count: int, evidence: list<array{name: string, url: string}>}>,
 *     exceptions: list<array{name: string, employee_status: string, hod_decision: ?string, reason: ?string}>,
 *     audit_summary: list<string>,
 * } $payload
 */
class SeoDailyCardReportMail extends Mailable
{
    use Queueable;

    public function __construct(public array $payload) {}

    public function build(): self
    {
        return $this
            ->subject("SEO daily card — {$this->payload['employee_name']} — {$this->payload['work_date']}")
            ->markdown('mail.seo-daily-card-report', ['payload' => $this->payload]);
    }
}
