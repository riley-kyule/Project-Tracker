<?php

namespace App\Services\Reports;

use App\Models\Task;
use Illuminate\Support\Str;

/**
 * The optional "description" and "N/M checklist items completed" lines shown
 * under a task in a report email, alongside its label/url. Kept separate
 * from each builder's own label logic (completion note vs. plain title,
 * etc.) since these two fields are identical everywhere a task is listed.
 * Callers must eager-load checklist_items_count / completed_checklist_items_count
 * (see BoardController::show() for the withCount() shape) — this reads them
 * rather than querying, to avoid N+1s across a list of tasks.
 */
class TaskReportDetails
{
    /** @return array{description: ?string, checklist_progress: ?string} */
    public static function forTask(Task $task): array
    {
        $total = $task->checklist_items_count ?? 0;

        return [
            'description' => $task->description ? Str::limit(trim(strip_tags($task->description)), 200) : null,
            'checklist_progress' => $total > 0 ? ($task->completed_checklist_items_count ?? 0)."/{$total}" : null,
        ];
    }
}
