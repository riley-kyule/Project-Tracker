<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TimeEntryController extends Controller
{
    public function start(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('view', $task);

        if (TimeEntry::query()->where('user_id', $request->user()->id)->whereNull('ended_at')->exists()) {
            throw ValidationException::withMessages(['timer' => 'You already have a running timer. Stop it before starting another.']);
        }

        $task->timeEntries()->create([
            'user_id' => $request->user()->id,
            'started_at' => now(),
            'source' => TimeEntry::SOURCE_TIMER,
            'work_location' => $request->string('work_location', 'unspecified')->toString(),
        ]);

        return back();
    }

    public function stop(Request $request, TimeEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);
        abort_unless($entry->isRunning(), 422, 'This timer is not running.');

        $entry->update([
            'ended_at' => now(),
            'duration_seconds' => (int) $entry->started_at->diffInSeconds(now()),
        ]);

        $entry->trackable?->recalculateActualMinutes();

        return back();
    }

    public function storeManual(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('view', $task);

        $validated = $request->validate([
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'work_location' => ['sometimes', Rule::in(['unspecified', 'remote', 'office', 'onsite'])],
            'adjustment_reason' => ['required', 'string', 'max:500'],
        ]);

        $task->timeEntries()->create([
            'user_id' => $request->user()->id,
            'duration_seconds' => $validated['duration_minutes'] * 60,
            'source' => TimeEntry::SOURCE_MANUAL,
            'work_location' => $validated['work_location'] ?? 'unspecified',
            'adjustment_status' => TimeEntry::STATUS_PENDING,
            'adjustment_reason' => $validated['adjustment_reason'],
        ]);

        return back();
    }

    public function approve(Request $request, TimeEntry $entry): RedirectResponse
    {
        $task = $entry->trackable;
        abort_unless($task instanceof Task, 404);
        Gate::authorize('approveTimeEntry', $task);

        abort_unless($entry->adjustment_status === TimeEntry::STATUS_PENDING, 422, 'This entry is not pending approval.');

        $entry->update(['adjustment_status' => TimeEntry::STATUS_APPROVED, 'approved_by' => $request->user()->id]);
        $task->recalculateActualMinutes();

        return back();
    }

    public function reject(Request $request, TimeEntry $entry): RedirectResponse
    {
        $task = $entry->trackable;
        abort_unless($task instanceof Task, 404);
        Gate::authorize('approveTimeEntry', $task);

        abort_unless($entry->adjustment_status === TimeEntry::STATUS_PENDING, 422, 'This entry is not pending approval.');

        $entry->update(['adjustment_status' => TimeEntry::STATUS_REJECTED, 'approved_by' => $request->user()->id]);

        return back();
    }
}
