<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\SavedFilter;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const TASK_FILTERS = ['all', 'due_today', 'overdue', 'blocked', 'awaiting_review', 'ceo_priority', 'completed_week', 'unassigned'];

    /** Direct columns only — Board/Column/Assignee/Department are relations and would need a join to sort by. */
    public const TASK_SORTABLE_COLUMNS = ['task_number', 'title', 'priority', 'due_at'];

    /** Widest range remoteSupport() will materialize into memory in one request, for both the on-screen report and its CSV export. */
    private const MAX_REMOTE_SUPPORT_RANGE_DAYS = 366;

    public function tasks(Request $request): Response
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $filter = $request->string('filter', 'all')->toString();
        abort_unless(in_array($filter, self::TASK_FILTERS, true), 404);

        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $sortIsValid = in_array($sort, self::TASK_SORTABLE_COLUMNS, true);

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

        // Department managers see their department only — and, within it, only
        // tasks they're actually authorized to view (visibleTo excludes e.g. a
        // restricted board they aren't a member of; it's a no-op for CEO/Admin).
        if (! $request->user()->hasAnyRole(['CEO', 'Administrator'])) {
            $query->where('department_id', $request->user()->department_id)->visibleTo($request->user());
        }

        $query->when(
            $sortIsValid,
            fn ($q) => $q->orderBy($sort, $direction),
            fn ($q) => $q->orderByRaw('due_at nulls last'),
        );

        return Inertia::render('reports/tasks', [
            'tasks' => $query->paginate(50)->withQueryString(),
            'filter' => $filter,
            'filters' => self::TASK_FILTERS,
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'people' => User::query()->where('status', User::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name']),
            'selected' => $request->only(['department_id', 'assignee_id']),
            'sort' => $sortIsValid ? $sort : null,
            'direction' => $direction,
            'savedFilters' => SavedFilter::query()
                ->where('user_id', $request->user()->id)
                ->where('scope', SavedFilter::SCOPE_REPORTS_TASKS)
                ->orderBy('name')
                ->get(['id', 'name', 'filters']),
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

        // visibleTo($request->user()) scopes every count to what the viewing
        // manager is actually authorized to see (a no-op for CEO/Admin) — without
        // it, a department manager's workload counts would include tasks on a
        // restricted board they can't open themselves.
        $people = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with('department:id,name')
            ->withCount([
                'assignedOpenTasks as open_tasks' => fn ($q) => $q->visibleTo($request->user()),
                'assignedOverdueTasks as overdue_tasks' => fn ($q) => $q->visibleTo($request->user()),
                'assignedOpenTasks as blocked_tasks' => fn ($q) => $q->visibleTo($request->user())->whereHas('column', fn ($c) => $c->where('semantic_status', 'blocked')),
                'assignedOpenTasks as awaiting_review_tasks' => fn ($q) => $q->visibleTo($request->user())->whereHas('column', fn ($c) => $c->where('semantic_status', 'review')),
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

        if ($from->diffInDays($to) > self::MAX_REMOTE_SUPPORT_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'from' => 'The report range cannot exceed '.self::MAX_REMOTE_SUPPORT_RANGE_DAYS.' days — narrow the date range and try again.',
            ]);
        }

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

    /**
     * A cell that Excel/Sheets opens and reads as starting with =, +, - or @
     * is interpreted as a formula, not text — a ticket title like
     * "=cmd|'/c calc'!A1" would execute when the export is opened. Prefixing
     * with a single quote forces spreadsheet software to treat it as a
     * literal string instead.
     */
    private function csvSafe(?string $value): ?string
    {
        if ($value !== null && Str::startsWith($value, ['=', '+', '-', '@'])) {
            return "'".$value;
        }

        return $value;
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
                    $this->csvSafe($ticket->title),
                    $this->csvSafe($ticket->department?->name),
                    $this->csvSafe($ticket->category?->name),
                    $ticket->priority,
                    $this->csvSafe($ticket->resolution_method),
                    $ticket->created_at->toDateTimeString(),
                    $ticket->first_responded_at ? (int) round($ticket->created_at->diffInMinutes($ticket->first_responded_at)) : null,
                    $ticket->resolved_at ? (int) round($ticket->created_at->diffInMinutes($ticket->resolved_at)) : null,
                    $ticket->time_spent_minutes,
                ]);
            }

            fclose($out);
        }, 'remote-support-report.csv', ['Content-Type' => 'text/csv']);
    }
}
