<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Board;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Mention;
use App\Models\PublicHoliday;
use App\Models\SlaPolicy;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WordPressUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function ceo(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['CEO', 'Administrator']), 403);

        $open = fn (): Builder => Task::query()->whereNull('completed_at')->whereNull('archived_at');

        $openTickets = Ticket::query()->whereIn('status', Ticket::OPEN_STATUSES);

        return Inertia::render('dashboard/ceo', [
            // The six headline numbers the dashboard actually shows — see
            // dashboard/ceo.tsx. Everything else (due today, CEO-priority
            // count, lifetime completions, …) lives in the sections below,
            // which compute their own data.
            'counts' => [
                'overdue' => $open()->where('due_at', '<', now())->count(),
                'blocked' => $open()->whereHas('column', fn ($q) => $q->where('semantic_status', 'blocked'))->count(),
                'awaiting_review' => $open()->whereHas('column', fn ($q) => $q->where('semantic_status', 'review'))->count(),
                'completed_week' => Task::query()->where('completed_at', '>=', now()->startOfWeek())->count(),
                'critical_tickets' => (clone $openTickets)->where('priority', 'critical')->count(),
            ],
            'departmentPerformance' => $this->departmentPerformance(null, $request->user()),
            'workload' => User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->withCount(['assignedOpenTasks as open_tasks'])
                ->orderByDesc('open_tasks')
                ->limit(10)
                ->get(['id', 'name'])
                ->filter(fn (User $user) => $user->open_tasks > 0)
                ->values(),
            'ceoPriorityTasks' => $open()
                ->where('ceo_priority', true)
                ->with(['board:id,name', 'assignee:id,name'])
                ->orderByRaw('due_at nulls last')
                ->limit(10)
                ->get(),
            'upcoming' => $open()
                ->whereBetween('due_at', [now(), now()->addDays(7)])
                ->with(['board:id,name', 'assignee:id,name'])
                ->orderBy('due_at')
                ->limit(10)
                ->get(),
            'recentActivity' => AuditLog::query()
                ->with('actor:id,name')
                ->latest('created_at')
                ->limit(12)
                ->get(),
            'leaveOverview' => $this->leaveOverview(),
            // Staff-only summary (already synced/cached in Postgres, no live WP calls
            // on dashboard load) — the full cross-site management page lives at
            // /admin/wordpress-users, this is read-only visibility for the CEO.
            'wordpressStaff' => WordPressUser::query()
                ->staffOnly()
                ->with('site:id,name,domain')
                ->orderBy('email')
                ->get(['id', 'wordpress_site_id', 'username', 'email', 'display_name', 'roles']),
        ]);
    }

    /** Leave snapshot for the CEO dashboard: open requests, upcoming leave, holidays. */
    private function leaveOverview(): array
    {
        $format = fn (LeaveRequest $r) => [
            'id' => $r->id,
            'employee' => $r->employee->full_name,
            'type' => $r->leaveType->name,
            'days' => (float) $r->days,
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
        ];

        $withRelations = fn ($q) => $q->with(['employee:id,first_name,middle_name,last_name', 'leaveType:id,name']);

        return [
            'open_count' => LeaveRequest::query()->where('status', LeaveRequest::STATUS_PENDING)->count(),
            'open' => $withRelations(LeaveRequest::query())
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->orderBy('start_date')->limit(8)->get()->map($format),
            'upcoming' => $withRelations(LeaveRequest::query())
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereBetween('start_date', [today()->toDateString(), today()->addDays(30)->toDateString()])
                ->orderBy('start_date')->limit(10)->get()->map($format),
            'holidays' => PublicHoliday::occurrencesBetween(today(), today()->addDays(60)),
        ];
    }

    /**
     * Three grouped queries instead of three-per-department: avoids an
     * N+1 that would otherwise scale with department count. $user scopes
     * out tasks the viewer can't actually see (e.g. a restricted board they
     * aren't a member of) — see Task::scopeVisibleTo(). It's a no-op for
     * CEO/Administrator, so passing it from ceo() changes nothing there;
     * it matters for department(), whose caller isn't always an executive.
     */
    private function departmentPerformance(?Collection $departments = null, ?User $user = null): Collection
    {
        $counts = fn (Builder $query) => $query
            ->when($user, fn ($q) => $q->visibleTo($user))
            ->whereNotNull('department_id')
            ->selectRaw('department_id, count(*) as total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        $open = fn (): Builder => Task::query()->whereNull('completed_at')->whereNull('archived_at');

        $openByDept = $counts($open());
        $overdueByDept = $counts($open()->where('due_at', '<', now()));
        $completedWeekByDept = $counts(Task::query()->where('completed_at', '>=', now()->startOfWeek()));
        $completedTotalByDept = $counts(Task::query()->whereNotNull('completed_at'));

        return ($departments ?? Department::query()->active()->orderBy('name')->get(['id', 'name']))
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'open' => $openByDept->get($department->id, 0),
                'overdue' => $overdueByDept->get($department->id, 0),
                'completed_week' => $completedWeekByDept->get($department->id, 0),
                'completed_total' => $completedTotalByDept->get($department->id, 0),
            ]);
    }

    public function department(Request $request): Response
    {
        $user = $request->user();

        $department = $user->hasAnyRole(['CEO', 'Administrator']) && $request->filled('department_id')
            ? Department::query()->findOrFail($request->integer('department_id'))
            : ($user->department ?? abort(404, 'You are not attached to a department.'));

        abort_unless(
            $user->hasAnyRole(['CEO', 'Administrator'])
                || ($user->hasRole('Department Manager') && $user->department_id === $department->id)
                || $department->leads($user->id),
            403,
        );

        $children = $department->children()->orderBy('name')->get(['id', 'name']);
        $departmentIds = $department->descendantIds();
        $canSwitchDepartment = $user->hasAnyRole(['CEO', 'Administrator']);

        // visibleTo($user) keeps this dashboard from leaking tasks on boards the
        // viewer can't actually open (a restricted board they aren't a member of,
        // or a confidential task without a grant) — whereIn('department_id', ...)
        // alone doesn't know about board/task-level visibility.
        $deptTasks = fn (): Builder => Task::query()
            ->whereIn('department_id', $departmentIds)
            ->visibleTo($user)
            ->whereNull('completed_at')
            ->whereNull('archived_at');

        return Inertia::render('dashboard/department', [
            'department' => $department->only(['id', 'name']),
            'subDepartments' => $children->isNotEmpty() ? $this->departmentPerformance($children, $user) : null,
            'canSwitchDepartment' => $canSwitchDepartment,
            'allDepartments' => $canSwitchDepartment
                ? Department::query()->active()->orderBy('name')->get(['id', 'name'])
                : [],
            'counts' => [
                'open' => $deptTasks()->count(),
                'unassigned' => $deptTasks()->whereNull('primary_assignee_id')->count(),
                'overdue' => $deptTasks()->where('due_at', '<', now())->count(),
                'blocked' => $deptTasks()->whereHas('column', fn ($q) => $q->where('semantic_status', 'blocked'))->count(),
                'awaiting_review' => $deptTasks()->whereHas('column', fn ($q) => $q->where('semantic_status', 'review'))->count(),
                'open_tickets' => Ticket::query()->whereIn('department_id', $departmentIds)->whereIn('status', Ticket::OPEN_STATUSES)->count(),
            ],
            'workload' => User::query()
                ->whereIn('department_id', $departmentIds)
                ->where('status', User::STATUS_ACTIVE)
                ->withCount([
                    'assignedOpenTasks as open_tasks' => fn ($q) => $q->visibleTo($user),
                    'assignedOverdueTasks as overdue_tasks' => fn ($q) => $q->visibleTo($user),
                ])
                ->orderByDesc('open_tasks')
                ->get(['id', 'name', 'job_title']),
            'unassigned' => $deptTasks()
                ->whereNull('primary_assignee_id')
                ->with('board:id,name')
                ->orderByRaw('due_at nulls last')
                ->limit(10)
                ->get(),
            'upcoming' => $deptTasks()
                ->whereBetween('due_at', [now(), now()->addDays(7)])
                ->with(['board:id,name', 'assignee:id,name'])
                ->orderBy('due_at')
                ->limit(10)
                ->get(),
            'recentlyCompleted' => Task::query()
                ->whereIn('department_id', $departmentIds)
                ->visibleTo($user)
                ->whereNotNull('completed_at')
                ->with(['board:id,name', 'assignee:id,name'])
                ->latest('completed_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function employee(Request $request): Response
    {
        $user = $request->user();

        $mine = fn (): Builder => Task::query()
            ->where('primary_assignee_id', $user->id)
            ->whereNull('completed_at')
            ->whereNull('archived_at');

        return Inertia::render('dashboard', [
            'counts' => [
                'open' => $mine()->count(),
                'due_today' => $mine()->whereDate('due_at', today())->count(),
                'overdue' => $mine()->where('due_at', '<', now())->count(),
                'blocked' => $mine()->whereHas('column', fn ($query) => $query->where('semantic_status', 'blocked'))->count(),
                'awaiting_review' => $mine()->whereHas('column', fn ($query) => $query->where('semantic_status', 'review'))->count(),
                'completed_today' => Task::query()
                    ->where('primary_assignee_id', $user->id)
                    ->whereDate('completed_at', today())
                    ->count(),
                'completed_total' => Task::query()
                    ->where('primary_assignee_id', $user->id)
                    ->whereNotNull('completed_at')
                    ->count(),
            ],
            'myTasks' => $mine()
                ->with(['board:id,name', 'column:id,name,semantic_status', 'labels:id,name,color'])
                ->orderByRaw('due_at nulls last')
                ->limit(15)
                ->get(),
            'recentlyAssigned' => $mine()
                ->with('board:id,name')
                ->latest('updated_at')
                ->limit(5)
                ->get(['id', 'task_number', 'title', 'board_id', 'due_at', 'priority']),
            // Tickets the user raised themselves and tickets assigned to them to
            // work — an agent (e.g. IT) rarely files tickets against themselves,
            // so scoping this to requester_id alone left their entire open queue
            // invisible on their own dashboard.
            'myTickets' => Ticket::query()
                ->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('assigned_to', $user->id))
                ->whereIn('status', Ticket::OPEN_STATUSES)
                ->with('category:id,name')
                ->latest()
                ->limit(5)
                ->get(['id', 'ticket_number', 'title', 'status', 'category_id', 'created_at', 'requester_id', 'assigned_to']),
            // Tasks someone else is waiting on this user to review/approve —
            // distinct from "awaiting_review" above, which is this user's own
            // work sitting in a review column regardless of who the reviewer is.
            'waitingOnMe' => Task::query()
                ->where('approver_id', $user->id)
                ->where('approval_status', Task::APPROVAL_PENDING)
                ->with('board:id,name')
                ->latest('updated_at')
                ->limit(10)
                ->get(['id', 'task_number', 'title', 'board_id', 'approval_note']),
            'mentions' => Mention::query()
                ->where('mentioned_user_id', $user->id)
                ->with(['comment' => fn ($query) => $query->with(['user:id,name', 'commentable'])])
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->filter(fn (Mention $mention) => $mention->comment?->commentable instanceof Task
                    && Gate::forUser($user)->allows('view', $mention->comment->commentable))
                ->map(fn (Mention $mention) => [
                    'id' => $mention->id,
                    'author' => $mention->comment->user?->name ?? 'Unknown',
                    'body' => str($mention->comment->body)->limit(140)->toString(),
                    'task_id' => $mention->comment->commentable->id,
                    'task_title' => $mention->comment->commentable->title,
                    'board_id' => $mention->comment->commentable->board_id,
                    'created_at' => $mention->created_at,
                ])
                ->values(),
            'quickCaptureBoards' => Board::query()
                ->where('is_active', true)
                ->with(['columns' => fn ($query) => $query->orderBy('position')])
                ->get()
                ->filter(fn (Board $board) => Gate::forUser($user)->allows('create', [Task::class, $board]))
                ->map(fn (Board $board) => [
                    'id' => $board->id,
                    'name' => $board->name,
                    'default_column_id' => $board->columns
                        ->first(fn ($column) => ! $column->is_completion_column && ! $column->is_archive_column)
                        ?->id,
                ])
                ->filter(fn (array $board) => $board['default_column_id'] !== null)
                ->values(),
        ]);
    }

    public function it(Request $request): Response
    {
        abort_unless($request->user()->can('tickets.manage'), 403);

        $open = Ticket::query()->whereIn('status', Ticket::OPEN_STATUSES);
        $recentWindow = now()->subDays(30);

        $recent = Ticket::query()
            ->where('created_at', '>=', $recentWindow)
            ->get(['created_at', 'first_responded_at', 'resolved_at']);

        $avgMinutes = function (string $column) use ($recent): ?int {
            $diffs = $recent->whereNotNull($column)
                ->map(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->{$column}));

            return $diffs->isEmpty() ? null : (int) round($diffs->avg());
        };

        $slaPolicies = SlaPolicy::query()->where('is_active', true)->get()->keyBy('priority');

        // Distinct from "overdue" above: a ticket can still be inside its resolution
        // window but already past the window a technician was due to acknowledge it.
        $responseBreached = (clone $open)
            ->whereNull('first_responded_at')
            ->get(['id', 'priority', 'created_at'])
            ->filter(fn (Ticket $ticket) => ($policy = $slaPolicies->get($ticket->priority))
                && $ticket->created_at->copy()->addMinutes($policy->first_response_minutes)->isPast())
            ->count();

        return Inertia::render('dashboard/it', [
            'counts' => [
                'new' => Ticket::query()->where('status', Ticket::STATUS_NEW)->count(),
                'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
                'critical' => (clone $open)->where('priority', 'critical')->count(),
                'overdue' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'response_breached' => $responseBreached,
                'waiting' => Ticket::query()->whereIn('status', [Ticket::STATUS_WAITING_USER, Ticket::STATUS_WAITING_THIRD_PARTY])->count(),
                'resolved_today' => Ticket::query()->whereDate('resolved_at', today())->count(),
            ],
            'averages' => [
                'first_response_minutes' => $avgMinutes('first_responded_at'),
                'resolution_minutes' => $avgMinutes('resolved_at'),
            ],
            'resolutionMethods' => Ticket::query()
                ->where('resolved_at', '>=', $recentWindow)
                ->whereNotNull('resolution_method')
                ->groupBy('resolution_method')
                ->select('resolution_method', DB::raw('count(*) as total'))
                ->pluck('total', 'resolution_method'),
            'byCategory' => (clone $open)
                ->join('ticket_categories', 'ticket_categories.id', '=', 'tickets.category_id')
                ->groupBy('ticket_categories.name')
                ->select('ticket_categories.name', DB::raw('count(*) as total'))
                ->orderByDesc('total')
                ->pluck('total', 'name'),
            'queue' => (clone $open)
                ->with(['requester:id,name', 'assignee:id,name', 'category:id,name'])
                ->orderByRaw("case priority when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
                ->orderBy('due_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
