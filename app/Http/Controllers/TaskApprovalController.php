<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskApprovalDecided;
use App\Notifications\TaskApprovalRequested;
use App\Services\AuditLogger;
use App\Services\TaskMover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskApprovalController extends Controller
{
    public function request(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate(['reviewer_id' => ['required', 'integer', 'exists:users,id']]);

        $reviewer = User::query()->findOrFail($validated['reviewer_id']);
        abort_unless(Gate::forUser($reviewer)->allows('view', $task), 422, 'The reviewer cannot see this task.');

        $task->update([
            'approval_status' => Task::APPROVAL_PENDING,
            'approver_id' => $reviewer->id,
            'approved_at' => null,
            'approval_note' => null,
        ]);

        AuditLogger::log($task, 'approval_requested', [], ['approver_id' => $reviewer->id]);

        if ($reviewer->id !== $request->user()->id && $reviewer->wantsNotification('task_approval_requested')) {
            $reviewer->notify(new TaskApprovalRequested($task, $request->user()));
        }

        return back();
    }

    public function approve(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('reviewApproval', $task);
        abort_unless($task->approval_status === Task::APPROVAL_PENDING, 422, 'This task has no pending approval.');

        $task->update(['approval_status' => Task::APPROVAL_APPROVED, 'approved_at' => now()]);

        AuditLogger::log($task, 'approval_granted', [], []);
        $this->notifyInterestedParties($task, $request->user()->id);

        return back();
    }

    public function reject(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('reviewApproval', $task);
        abort_unless($task->approval_status === Task::APPROVAL_PENDING, 422, 'This task has no pending approval.');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($task, $validated) {
            $task->update([
                'approval_status' => Task::APPROVAL_REJECTED,
                'approval_note' => $validated['reason'],
            ]);

            $activeColumn = BoardColumn::query()
                ->where('board_id', $task->board_id)
                ->where('semantic_status', 'active')
                ->first();

            if ($activeColumn !== null && $activeColumn->id !== $task->board_column_id) {
                TaskMover::move($task, $activeColumn, 1);
            }

            AuditLogger::log($task, 'approval_rejected', [], ['reason' => $validated['reason']]);
        });

        $this->notifyInterestedParties($task, $request->user()->id);

        return back();
    }

    private function notifyInterestedParties(Task $task, int $actorId): void
    {
        foreach (array_unique(array_filter([$task->created_by, $task->primary_assignee_id])) as $userId) {
            if ($userId === $actorId) {
                continue;
            }

            $user = User::find($userId);

            if ($user?->wantsNotification('task_approval_decided')) {
                $user->notify(new TaskApprovalDecided($task));
            }
        }
    }
}
