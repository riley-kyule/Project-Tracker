<?php

namespace App\Http\Controllers;

use App\Models\RecurrenceRule;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RecurrenceRuleController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('manageRecurrence', $task);

        abort_if($task->recurrence_rule_id !== null, 422, 'This task already has a recurrence rule.');

        $validated = $request->validate([
            'frequency' => ['required', Rule::in(RecurrenceRule::FREQUENCIES)],
            'interval_value' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'schedule_config' => ['sometimes', 'array'],
        ]);

        $rule = RecurrenceRule::create([
            ...$validated,
            'template_task_id' => $task->id,
            'created_by' => $request->user()->id,
        ]);

        if ($rule->frequency !== 'after_completion') {
            $rule->update(['next_run_at' => $rule->calculateNextRun(now())]);
        }

        $task->update(['recurrence_rule_id' => $rule->id]);

        return back();
    }

    public function update(Request $request, RecurrenceRule $rule): RedirectResponse
    {
        Gate::authorize('manageRecurrence', $rule->template);

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'interval_value' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $rule->update($validated);

        return back();
    }
}
