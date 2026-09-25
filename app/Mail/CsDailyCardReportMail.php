<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

/**
 * The midnight per-employee daily report — Customer Service Board
 * Requirements Specification v1.0 §11 — one email covering both the score
 * card snapshot and that person's Kanban activity for the day. Sent synchronously from within
 * GenerateCsDailyCardReport, which is itself the queued unit of work.
 * Mirrors App\Mail\SeoDailyCardReportMail.
 *
 * @param array{
 *     employee_name: string, role: ?string, department: string, work_date: string, card_id: int, closed_at: string,
 *     planned_points: int, employee_submitted_points: ?float, approved_points: ?float, quota_percentage: ?float,
 *     items: list<array{name: string, section: string, weight: float, target_quantity: ?float, achieved_quantity: ?float, employee_status: string, submitted_at: ?string, hod_decision: ?string, evidence_count: int, evidence: list<array{name: string, url: string}>}>,
 *     commercial: array{new_customers: int, new_customer_revenue: float, renewed_customers: int, retained_revenue: float, currency: string},
 *     kanban?: array{counts: array<string, int>, created: list<array{title: string, url: string}>, moved: list<array{title: string, url: string}>, completed: list<array{title: string, url: string}>, blocked: list<array{title: string, url: string}>, overdue: list<array{title: string, url: string}>},
 *     exceptions: list<array{name: string, employee_status: string, hod_decision: ?string, reason: ?string}>,
 *     audit_summary: list<string>,
 * } $payload
 */
class CsDailyCardReportMail extends Mailable
{
    use Queueable;

    public function __construct(public array $payload) {}

    public function build(): self
    {
        return $this
            ->subject("Customer Service daily card — {$this->payload['employee_name']} — {$this->payload['work_date']}")
            ->markdown('mail.cs-daily-card-report', ['payload' => $this->payload]);
    }
}
