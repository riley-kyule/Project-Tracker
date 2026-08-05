<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Task;
use Illuminate\Support\Carbon;

/**
 * Decides whether a recurring task is due to reset right now. Mirrors
 * RunScheduledBackup's period-start pattern: a task is due once the fixed
 * "morning" hour has passed today (company timezone) and it hasn't already
 * been reset within its own frequency's current period — last_auto_reset_at
 * is the source of truth for that, not a separately-tracked next-run field.
 */
class TaskAutoResetSchedule
{
    private const RESET_HOUR = '06:00';

    public function timezone(): string
    {
        return CompanySetting::current()->timezone ?: 'Africa/Nairobi';
    }

    public function isDue(Task $task, Carbon $now): bool
    {
        if ($task->auto_reset_frequency === null || $task->archived_at !== null) {
            return false;
        }

        if ($now->format('H:i') < self::RESET_HOUR) {
            return false;
        }

        $periodStart = (match ($task->auto_reset_frequency) {
            'weekly' => $now->copy()->startOfWeek(),
            'monthly' => $now->copy()->startOfMonth(),
            default => $now->copy()->startOfDay(),
        })->utc();

        return $task->last_auto_reset_at === null || $task->last_auto_reset_at->lt($periodStart);
    }
}
