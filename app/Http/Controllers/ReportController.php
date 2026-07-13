<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Company-wide (or department-scoped) workload and exception counts per
     * employee, distinct from the tasks report: this is the aggregate view,
     * not filtered rows.
     */
    public function workload(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $isExec = $request->user()->hasAnyRole(['CEO', 'Administrator']);
        $departmentId = $isExec ? $request->integer('department_id') ?: null : $request->user()->department_id;

        $people = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with('department:id,name')
            ->withCount([
                'assignedOpenTasks as open_tasks',
                'assignedOverdueTasks as overdue_tasks',
                'assignedOpenTasks as blocked_tasks' => fn ($q) => $q->whereHas('column', fn ($c) => $c->where('semantic_status', 'blocked')),
                'assignedOpenTasks as awaiting_review_tasks' => fn ($q) => $q->whereHas('column', fn ($c) => $c->where('semantic_status', 'review')),
            ])
            ->orderByDesc('open_tasks')
            ->get(['id', 'name', 'department_id', 'job_title']);

        return Inertia::render('reports/workload', [
            'people' => $people,
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'selected' => ['department_id' => $departmentId],
            'canFilterDepartment' => $isExec,
        ]);
    }

    public function remoteSupport(Request $request): Response|StreamedResponse
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $from = $request->date('from') ?? now()->subDays(30)->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();

        $resolved = Ticket::query()
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->when(
                ! $request->user()->hasAnyRole(['CEO', 'Administrator']),
                fn ($q) => $q->where('department_id', $request->user()->department_id),
            )
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->with(['department:id,name', 'category:id,name'])
            ->get();

        if ($request->string('format')->toString() === 'csv') {
            return $this->remoteSupportCsv($resolved);
        }

        $byMethod = $resolved->groupBy('resolution_method')->map->count();
        $reopened = TicketStatusHistory::query()
            ->whereIn('ticket_id', $resolved->pluck('id'))
            ->where('to_status', Ticket::STATUS_REOPENED)
            ->distinct('ticket_id')
            ->count('ticket_id');

        $avg = function (string $column) use ($resolved): ?int {
            $diffs = $resolved->whereNotNull($column)
                ->map(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->{$column}));

            return $diffs->isEmpty() ? null : (int) round($diffs->avg());
        };

        return Inertia::render('reports/remote-support', [
            'totals' => [
                'resolved' => $resolved->count(),
                'avg_first_response_minutes' => $avg('first_responded_at'),
                'avg_resolution_minutes' => $avg('resolved_at'),
                'avg_time_spent_minutes' => $resolved->isEmpty() ? null : (int) round($resolved->avg('time_spent_minutes')),
                'reopen_rate' => $resolved->isEmpty() ? null : round($reopened / $resolved->count() * 100, 1),
            ],
            'byMethod' => $byMethod,
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'selected' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'department_id' => $request->input('department_id'),
            ],
        ]);
    }

    /** Export matches the on-screen filters exactly (US-060). */
    private function remoteSupportCsv($resolved): StreamedResponse
    {
        return response()->streamDownload(function () use ($resolved) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['ticket_number', 'title', 'department', 'category', 'priority', 'resolution_method', 'created_at', 'first_response_minutes', 'resolution_minutes', 'time_spent_minutes']);

            foreach ($resolved as $ticket) {
                fputcsv($out, [
                    'TK-'.$ticket->ticket_number,
                    $ticket->title,
                    $ticket->department?->name,
                    $ticket->category?->name,
                    $ticket->priority,
                    $ticket->resolution_method,
                    $ticket->created_at->toDateTimeString(),
                    $ticket->first_responded_at ? $ticket->created_at->diffInMinutes($ticket->first_responded_at) : null,
                    $ticket->resolved_at ? $ticket->created_at->diffInMinutes($ticket->resolved_at) : null,
                    $ticket->time_spent_minutes,
                ]);
            }

            fclose($out);
        }, 'remote-support-report.csv', ['Content-Type' => 'text/csv']);
    }
}
