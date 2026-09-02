<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class PerformanceService
{
    /**
     * Move a cycle to `active` and open a review for every active employee,
     * with the reviewer defaulting to their line manager's user account.
     */
    public function activate(PerformanceCycle $cycle): int
    {
        return DB::transaction(function () use ($cycle) {
            $created = 0;

            Employee::query()->active()->with('manager.user')->each(function (Employee $employee) use ($cycle, &$created) {
                $review = $cycle->reviews()->firstOrCreate(
                    ['employee_id' => $employee->id],
                    [
                        'reviewer_id' => $employee->manager?->user_id,
                        'status' => PerformanceReview::STATUS_SELF_REVIEW,
                    ],
                );

                if ($review->wasRecentlyCreated) {
                    $created++;
                }
            });

            $cycle->update(['status' => 'active']);
            AuditLogger::log($cycle, 'performance_cycle_activated', [], ['reviews' => $created]);

            return $created;
        });
    }
}
