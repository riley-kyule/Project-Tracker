<?php

namespace App\Jobs;

use App\Mail\CsDailyCardReportMail;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\CsCardItem;
use App\Models\CsDailyCard;
use App\Models\Employee;
use App\Models\ReportDelivery;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Services\Cs\CsKanbanActivityBuilder;
use App\Services\Cs\CsNotificationRecipients;
use App\Services\Cs\CsPerformanceQuery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * One job per employee's Customer Service daily card — the midnight report,
 * Customer Service Board Requirements Specification v1.0 §11. Mirrors
 * App\Jobs\GenerateSeoDailyCardReport's shape and idempotency guard
 * (report_snapshots' partial unique index on (report_date, report_type,
 * user_id)) so a retry or a duplicate dispatch never sends the same day's
 * report twice.
 */
class GenerateCsDailyCardReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(public int $dailyCardId) {}

    public function handle(CsNotificationRecipients $recipientResolver, CsPerformanceQuery $query, CsKanbanActivityBuilder $kanban): void
    {
        $card = CsDailyCard::query()->with(['employee.user', 'department', 'items'])->find($this->dailyCardId);

        if ($card === null || $card->department === null) {
            return;
        }

        $employee = $card->employee;
        $user = $employee?->user;

        if ($employee === null || $user === null) {
            Log::warning('Customer Service daily card report skipped — employee has no linked EWMS user account.', ['card_id' => $card->id]);

            return;
        }

        $recipients = $recipientResolver->resolve($card->department);

        if ($recipients->isEmpty()) {
            Log::warning('Customer Service daily card report has no resolvable recipients — no HOD, CEO, or additional recipient configured.', ['card_id' => $card->id, 'department_id' => $card->department_id]);

            return;
        }

        $payload = $this->buildPayload($card, $employee, $query, $kanban);

        $snapshot = $this->recordSnapshot($card, $user, $payload);

        if ($snapshot === null) {
            return; // Unique constraint hit: another run already generated today's report for this employee.
        }

        $this->deliver($snapshot, $recipients, new CsDailyCardReportMail($payload));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateCsDailyCardReport job failed.', ['card_id' => $this->dailyCardId, 'error' => $exception->getMessage()]);
    }

    /** @return array<string, mixed> */
    private function buildPayload(CsDailyCard $card, Employee $employee, CsPerformanceQuery $query, CsKanbanActivityBuilder $kanban): array
    {
        $items = $card->items()->with('evidence')->orderBy('position')->get();

        $quotaPercentage = $card->approved_points !== null && $card->planned_points > 0
            ? round(((float) $card->approved_points / $card->planned_points) * 100, 1)
            : null;

        $itemRows = $items->map(fn (CsCardItem $item) => [
            'name' => $item->name,
            'section' => $item->section,
            'weight' => (float) $item->weight,
            'target_quantity' => $item->target_quantity !== null ? (float) $item->target_quantity : null,
            'achieved_quantity' => $item->achieved_quantity !== null ? (float) $item->achieved_quantity : null,
            'employee_status' => $item->employee_status,
            'submitted_at' => $item->submitted_at?->toDateTimeString(),
            'hod_decision' => $item->hod_decision,
            'evidence_count' => $item->evidence->count(),
            'evidence' => $item->evidence->map(fn ($attachment) => [
                'name' => $attachment->original_name,
                'url' => route('attachments.download', $attachment),
            ])->all(),
        ])->all();

        $exceptions = $items->filter(fn (CsCardItem $item) => in_array($item->employee_status, [CsCardItem::STATUS_BLOCKED, CsCardItem::STATUS_NOT_STARTED, CsCardItem::STATUS_IN_PROGRESS], true)
                || in_array($item->hod_decision, [CsCardItem::DECISION_REJECTED, CsCardItem::DECISION_MAJOR_REWORK, null], true))
            ->map(fn (CsCardItem $item) => [
                'name' => $item->name,
                'employee_status' => $item->employee_status,
                'hod_decision' => $item->hod_decision,
                'reason' => $item->hod_decision_reason ?? $item->blocker_reason,
            ])->values()->all();

        $auditSummary = AuditLog::query()
            ->where('auditable_type', (new CsCardItem)->getMorphClass())
            ->whereIn('auditable_id', $items->pluck('id'))
            ->whereDate('created_at', $card->work_date)
            ->get()
            ->map(fn (AuditLog $log) => Str::headline($log->event).' by '.($log->actor?->name ?? 'system').' at '.$log->created_at->format('H:i'))
            ->all();

        return [
            'employee_name' => $employee->full_name,
            'role' => $employee->job_title,
            'department' => $card->department->name,
            'work_date' => $card->work_date->toDateString(),
            'card_id' => $card->id,
            'closed_at' => $card->closed_at?->toDateTimeString() ?? now()->toDateTimeString(),
            'planned_points' => $card->planned_points,
            'employee_submitted_points' => $card->employee_submitted_points !== null ? (float) $card->employee_submitted_points : null,
            'approved_points' => $card->approved_points !== null ? (float) $card->approved_points : null,
            'quota_percentage' => $quotaPercentage,
            'items' => $itemRows,
            'commercial' => $query->commercialSummary($employee, $card->work_date->copy()),
            // The Kanban half of the one combined daily email: what this person did on the board that day.
            'kanban' => $kanban->forUser($employee->user, $card->work_date->copy(), CompanySetting::current()->timezone ?: 'Africa/Nairobi'),
            'exceptions' => $exceptions,
            'audit_summary' => $auditSummary,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function recordSnapshot(CsDailyCard $card, User $user, array $payload): ?ReportSnapshot
    {
        try {
            return ReportSnapshot::query()->create([
                'report_date' => $card->work_date,
                'report_type' => ReportSnapshot::TYPE_CS_DAILY_CARD,
                'department_id' => $card->department_id,
                'user_id' => $user->id,
                'generated_at' => now(),
                'payload' => json_decode(json_encode($payload), true),
                'status' => ReportSnapshot::STATUS_GENERATED,
                'version' => 1,
            ]);
        } catch (QueryException $e) {
            $isUniqueViolation = $e->getCode() === '23505' || str_contains($e->getMessage(), 'UNIQUE constraint failed');

            if (! $isUniqueViolation) {
                throw $e;
            }

            Log::info('Customer Service daily card report already generated for today, skipping duplicate.', ['card_id' => $card->id]);

            return null;
        }
    }

    /**
     * @param  Collection<int, array{type: string, user: ?User, email: ?string, name: ?string}>  $recipients
     */
    private function deliver(ReportSnapshot $snapshot, Collection $recipients, CsDailyCardReportMail $mail): void
    {
        foreach ($recipients as $recipient) {
            $delivery = ReportDelivery::query()->create([
                'report_snapshot_id' => $snapshot->id,
                'recipient_user_id' => $recipient['user']?->id,
                'recipient_email' => $recipient['user'] === null ? $recipient['email'] : null,
                'recipient_name' => $recipient['user'] === null ? $recipient['name'] : null,
                'status' => ReportDelivery::STATUS_PENDING,
            ]);

            try {
                Mail::to($recipient['user'] ?? $recipient['email'])->send($mail);

                $delivery->update(['status' => ReportDelivery::STATUS_SENT, 'sent_at' => now()]);
            } catch (Throwable $e) {
                $delivery->update([
                    'status' => ReportDelivery::STATUS_FAILED,
                    'failed_at' => now(),
                    'failure_reason' => Str::limit($e->getMessage(), 500),
                    'retry_count' => $delivery->retry_count + 1,
                ]);
            }
        }
    }
}
