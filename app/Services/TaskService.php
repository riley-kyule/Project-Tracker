<?php

namespace App\Services;

use App\Mail\TaskAssignedMail;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Extracted from TaskController::store() so a second caller (the MCP
 * create_task tool) gets identical behavior — position, task_number,
 * assignee sync, and the assignment notification — without duplicating it.
 * Callers are responsible for authorization (Gate::authorize('create', [Task::class, $board]))
 * and for resolving/validating $column and $data themselves, same division
 * of responsibility TicketService::submit() uses.
 */
class TaskService
{
    /** @param  array<string, mixed>  $data  Same shape as StoreTaskRequest::validated() — title, priority, due_at, primary_assignee_id, project_id, plus an MCP-only optional description. */
    public static function create(User $creator, Board $board, BoardColumn $column, array $data): Task
    {
        self::guardAssigneeIsActive($data['primary_assignee_id'] ?? null);

        $task = DB::transaction(function () use ($creator, $board, $column, $data) {
            $task = Task::create([
                ...$data,
                'board_id' => $board->id,
                'board_column_id' => $column->id,
                'department_id' => $board->department_id,
                'created_by' => $creator->id,
                'position' => (int) $column->tasks()->max('position') + 1,
            ]);

            $task->forceFill(['task_number' => $task->id])->save();

            AuditLogger::log($task, 'created', [], ['title' => $task->title]);

            return $task;
        });

        TaskAssigneeSync::syncPrimary($task, null);
        self::notifyAssignee($task, null, $creator);

        return $task;
    }

    /**
     * Deliberately not gated on board access, same as TaskAssigneeController
     * — becoming the primary assignee is itself how someone outside the
     * board's department gets access to this task (TaskPolicy::view()).
     */
    public static function guardAssigneeIsActive(?int $assigneeId): void
    {
        if ($assigneeId === null) {
            return;
        }

        $assignee = User::query()->findOrFail($assigneeId);

        if (! $assignee->isActive()) {
            throw ValidationException::withMessages([
                'primary_assignee_id' => 'The assignee must be active.',
            ]);
        }
    }

    public static function notifyAssignee(Task $task, ?int $previousAssigneeId, User $actor): void
    {
        if ($task->primary_assignee_id === null
            || $task->primary_assignee_id === $previousAssigneeId
            || $task->primary_assignee_id === $actor->id) {
            return;
        }

        if ($task->assignee?->wantsNotification('task_assigned')) {
            $task->assignee->notify(new TaskAssigned($task, $actor));
            Mail::to($task->assignee)->queue(new TaskAssignedMail($task, $actor));
            app(PushNotifier::class)->notify($task->assignee, 'task_assigned', [
                'title' => "New task: {$task->title}",
                'url' => url("/boards/{$task->board_id}?task={$task->id}"),
            ]);
        }
    }
}
