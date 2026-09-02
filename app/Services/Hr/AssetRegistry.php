<?php

namespace App\Services\Hr;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place asset custody changes. Keeps `assets.status` and the open
 * `asset_assignments` row in step, and writes an audit trail for both.
 */
class AssetRegistry
{
    /**
     * @param  array{expected_return_at?: string|null, condition_out?: string|null, notes?: string|null}  $details
     */
    public function assign(Asset $asset, Employee $employee, User $actor, array $details = []): AssetAssignment
    {
        if ($asset->currentAssignment()->exists()) {
            throw ValidationException::withMessages([
                'employee_id' => 'This asset is already assigned. Record its return first.',
            ]);
        }

        if (in_array($asset->status, [Asset::STATUS_RETIRED, Asset::STATUS_LOST], true)) {
            throw ValidationException::withMessages([
                'employee_id' => "A {$asset->status} asset can't be assigned.",
            ]);
        }

        return DB::transaction(function () use ($asset, $employee, $actor, $details) {
            $assignment = $asset->assignments()->create([
                'employee_id' => $employee->id,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'expected_return_at' => $details['expected_return_at'] ?? null,
                'condition_out' => $details['condition_out'] ?? $asset->condition,
                'notes' => $details['notes'] ?? null,
            ]);

            $asset->update(['status' => Asset::STATUS_ASSIGNED]);

            AuditLogger::log($asset, 'asset_assigned', [], [
                'employee_id' => $employee->id,
                'employee' => $employee->full_name,
            ]);

            return $assignment;
        });
    }

    /**
     * @param  array{condition_in?: string|null, notes?: string|null, new_status?: string|null}  $details
     */
    public function receiveReturn(AssetAssignment $assignment, User $actor, array $details = []): AssetAssignment
    {
        if (! $assignment->isOpen()) {
            throw ValidationException::withMessages([
                'assignment' => 'This assignment was already closed.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $actor, $details) {
            $assignment->update([
                'returned_at' => now(),
                'returned_to' => $actor->id,
                'condition_in' => $details['condition_in'] ?? null,
                'notes' => trim(($assignment->notes ? $assignment->notes."\n" : '').($details['notes'] ?? '')) ?: $assignment->notes,
            ]);

            $newStatus = $details['new_status'] ?? Asset::STATUS_IN_STOCK;
            $update = ['status' => $newStatus];

            if (! empty($details['condition_in'])) {
                $update['condition'] = $details['condition_in'];
            }

            $assignment->asset->update($update);

            AuditLogger::log($assignment->asset, 'asset_returned', [
                'employee_id' => $assignment->employee_id,
            ], ['status' => $newStatus]);

            return $assignment;
        });
    }
}
