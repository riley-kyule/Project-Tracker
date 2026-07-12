<?php

namespace App\Services;

use App\Models\BoardColumn;
use App\Models\RecurrenceRule;
use App\Models\Task;
use App\Notifications\RecurrenceMissed;
use Illuminate\Support\Facades\DB;

class RecurrenceService
{
    /** A run more than this late past its schedule is flagged as missed (WORKFLOWS.md). */
    private const MISSED_THRESHOLD_HOURS = 24;

    /** Scheduled sweep: generate instances for every due, non-event-driven rule. */
    public static function generateDueInstances(): int
    {
        $generated = 0;

        RecurrenceRule::query()
            ->where('is_active', true)
            ->where('frequency', '!=', 'after_completion')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->with('template', 'creator')
            ->each(function (RecurrenceRule $rule) use (&$generated) {
                $missed = $rule->next_run_at->diffInHours(now()) >= self::MISSED_THRESHOLD_HOURS;

                self::createInstance($rule);

                $rule->update(['next_run_at' => $rule->calculateNextRun($rule->next_run_at ?? now())]);

                if ($missed) {
                    $rule->creator?->notify(new RecurrenceMissed($rule));
                }

                $generated++;
            });

        return $generated;
    }

    /** Event-driven: called when a task belonging to an after_completion rule is completed. */
    public static function generateFromCompletion(Task $completedTask): ?Task
    {
        $rule = $completedTask->recurrenceRule;

        if ($rule === null || ! $rule->is_active || $rule->frequency !== 'after_completion') {
            return null;
        }

        return self::createInstance($rule, $completedTask);
    }

    public static function createInstance(RecurrenceRule $rule, ?Task $previous = null): Task
    {
        return DB::transaction(function () use ($rule, $previous) {
            $template = $rule->template;

            $column = BoardColumn::query()
                ->where('board_id', $template->board_id)
                ->orderBy('position')
                ->first();

            $task = Task::create([
                'title' => $template->title,
                'description' => $template->description,
                'department_id' => $template->department_id,
                'board_id' => $template->board_id,
                'board_column_id' => $column->id,
                'project_id' => $template->project_id,
                'created_by' => $rule->created_by,
                'primary_assignee_id' => $template->primary_assignee_id,
                'priority' => $template->priority,
                'position' => (int) $column->tasks()->max('position') + 1,
                'recurrence_rule_id' => $rule->id,
                'previous_recurrence_task_id' => $previous?->id ?? $rule->generatedTasks()->latest('id')->first()?->id,
            ]);

            $task->forceFill(['task_number' => $task->id])->save();
            $task->labels()->sync($template->labels()->pluck('labels.id'));

            AuditLogger::log($task, 'created_from_recurrence', [], ['recurrence_rule_id' => $rule->id]);

            $rule->update(['last_generated_at' => now()]);

            return $task;
        });
    }
}
