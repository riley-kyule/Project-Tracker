<?php

namespace App\Services\Cs;

use App\Models\CsContinuityCheck;
use App\Models\CsContinuityIssue;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use InvalidArgumentException;

/**
 * Customer Service Board Requirements Specification v1.0 §5.2 "Assigned
 * platform continuity": the five daily checks, plus the issue tracker
 * "reporting a fault is not sufficient closure" requires. Every mutation
 * goes through AuditLogger, which is what supplies §5.2's "updates" — no
 * separate log table needed.
 */
class CsContinuityService
{
    public function recordCheck(Employee $employee, array $data, User $actor): CsContinuityCheck
    {
        if (! in_array($data['check_type'], CsContinuityCheck::TYPES, true)) {
            throw new InvalidArgumentException("Unknown continuity check type: {$data['check_type']}");
        }

        $data['check_date'] ??= now()->toDateString();
        $data['status'] ??= CsContinuityCheck::STATUS_OK;

        if ($data['status'] === CsContinuityCheck::STATUS_ISSUE_FOUND && trim($data['notes'] ?? '') === '') {
            throw new InvalidArgumentException('Describe the issue found before submitting this check.');
        }

        $check = CsContinuityCheck::query()->create([...$data, 'employee_id' => $employee->id, 'recorded_by' => $actor->id]);

        AuditLogger::log($check, 'cs_continuity_check.recorded', [], $check->only(['check_type', 'status', 'notes']));

        return $check;
    }

    public function reportIssue(Employee $employee, array $data, User $actor): CsContinuityIssue
    {
        $data['first_reported_at'] ??= now();
        $data['status'] = CsContinuityIssue::STATUS_OPEN;

        $issue = CsContinuityIssue::query()->create([...$data, 'employee_id' => $employee->id, 'created_by' => $actor->id]);

        AuditLogger::log($issue, 'cs_continuity_issue.reported', [], $issue->only(['title', 'severity', 'owner', 'first_reported_at']));

        return $issue;
    }

    /**
     * The only path that closes an issue out — confirmed resolved, accepted
     * as a known exception, or reassigned, each with the final outcome
     * recorded, per §5.2 "previously reported issues checked until confirmed
     * resolved, accepted as a known exception or reassigned".
     */
    public function closeIssue(CsContinuityIssue $issue, string $status, string $finalOutcome, User $actor): CsContinuityIssue
    {
        if (! in_array($status, CsContinuityIssue::CLOSED_STATUSES, true)) {
            throw new InvalidArgumentException("Unknown close status: {$status}");
        }

        $old = $issue->only(['status', 'final_outcome', 'resolved_at', 'resolved_by']);
        $issue->update(['status' => $status, 'final_outcome' => $finalOutcome, 'resolved_at' => now(), 'resolved_by' => $actor->id]);

        AuditLogger::log($issue, 'cs_continuity_issue.closed', $old, $issue->only(['status', 'final_outcome', 'resolved_at', 'resolved_by']));

        return $issue;
    }

    /** An update to an open issue's owner, severity, or expected resolution — retained via the audit trail. */
    public function updateIssue(CsContinuityIssue $issue, array $data): CsContinuityIssue
    {
        $old = $issue->only(array_keys($data));
        $issue->update($data);

        AuditLogger::log($issue, 'cs_continuity_issue.updated', $old, $issue->only(array_keys($data)));

        return $issue;
    }

    public function reopenIssue(CsContinuityIssue $issue, string $reason): CsContinuityIssue
    {
        $old = $issue->only(['status', 'final_outcome', 'resolved_at']);
        $issue->update(['status' => CsContinuityIssue::STATUS_OPEN, 'final_outcome' => null, 'resolved_at' => null]);

        AuditLogger::log($issue, 'cs_continuity_issue.reopened', $old, ['status' => $issue->status, 'reason' => $reason]);

        return $issue;
    }
}
