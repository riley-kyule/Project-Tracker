<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public const TASK_FILTERS = ['all', 'due_today', 'overdue', 'blocked', 'awaiting_review', 'ceo_priority', 'completed_week', 'unassigned'];

    public function tasks(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $filter = $request->string('filter', 'all')->toString();
        abort_unless(in_array($filter, self::TASK_FILTERS, true), 404);

        $query = Task::query()
            ->with(['board:id,name', 'column:id,name,semantic_status', 'assignee:id,name', 'department:id,name'])
            ->when($filter !== 'completed_week', fn ($q) => $q->whereNull('completed_at')->whereNull('archived_at'))
            ->when($filter === 'due_today', fn ($q) => $q->whereDate('due_at', today()))
            ->when($filter === 'overdue', fn ($q) => $q->where('due_at', '<', now()))
            ->when($filter === 'blocked', fn ($q) => $q->whereHas('column', fn ($c) => $c->where('semantic_status', 'blocked')))
            ->when($filter === 'awaiting_review', fn ($q) => $q->whereHas('column', fn ($c) => $c->where('semantic_status', 'review')))
            ->when($filter === 'ceo_priority', fn ($q) => $q->where('ceo_priority', true))
            ->when($filter === 'completed_week', fn ($q) => $q->where('completed_at', '>=', now()->startOfWeek()))
            ->when($filter === 'unassigned', fn ($q) => $q->whereNull('primary_assignee_id'))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('assignee_id'), fn ($q) => $q->where('primary_assignee_id', $request->integer('assignee_id')));

        // Department managers see their department only.
        if (! $request->user()->hasAnyRole(['CEO', 'Administrator'])) {
            $query->where('department_id', $request->user()->department_id);
        }

        return Inertia::render('reports/tasks', [
            'tasks' => $query->orderByRaw('due_at nulls last')->paginate(50)->withQueryString(),
            'filter' => $filter,
            'filters' => self::TASK_FILTERS,
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'people' => User::query()->where('status', User::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name']),
            'selected' => $request->only(['department_id', 'assignee_id']),
        ]);
    }
}
