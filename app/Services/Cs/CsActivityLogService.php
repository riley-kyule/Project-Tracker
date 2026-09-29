<?php

namespace App\Services\Cs;

use App\Models\CsActivityRecord;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use InvalidArgumentException;

/**
 * Customer Service Board Requirements Specification v1.0 §5.1 "Daily sales
 * activity records" — logging and amending one contact attempt. Kept
 * separate from CsSalesAttributionService (the payment ledger): an activity
 * record is evidence that contact happened, whether or not it ever becomes a
 * sale; a sales record is the commercial outcome. A completed activity can
 * be linked to the sale it produced (sales_record_id) once one exists.
 */
class CsActivityLogService
{
    public function log(Employee $employee, array $data, User $actor): CsActivityRecord
    {
        if (! in_array($data['workstream'], CsActivityRecord::WORKSTREAMS, true)) {
            throw new InvalidArgumentException("Unknown workstream: {$data['workstream']}");
        }

        $record = CsActivityRecord::query()->create([
            ...$data,
            'employee_id' => $employee->id,
            'recorded_by' => $actor->id,
        ]);

        AuditLogger::log($record, 'cs_activity_record.logged', [], $record->only(['workstream', 'customer_identifier', 'stage']));

        return $record;
    }

    /** Moving the funnel stage forward, recording a response, or linking the eventual sale — the same record as the conversation with that customer progresses. */
    public function update(CsActivityRecord $record, array $data): CsActivityRecord
    {
        $old = $record->only(array_keys($data));
        $record->update($data);

        AuditLogger::log($record, 'cs_activity_record.updated', $old, $record->only(array_keys($data)));

        return $record;
    }
}
