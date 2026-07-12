<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'counts' => [
                'due_today' => $open()->whereDate('due_at', today())->count(),
                'overdue' => $open()->where('due_at', '<', now())->count(),
                'blocked' => $open()->whereHas('column', fn ($q) => $q->where('semantic_status', 'blocked'))->count(),
                'awaiting_review' => $open()->whereHas('column', fn ($q) => $q->where('semantic_status', 'review'))->count(),
                'ceo_priority' => $open()->where('ceo_priority', true)->count(),
                'completed_today' => Task::query()->whereDate('completed_at', today())->count(),
                'completed_week' => Task::query()->where('completed_at', '>=', now()->startOfWeek())->count(),
                'critical_tickets' => (clone $openTickets)->where('priority', 'critical')->count(),
                'overdue_tickets' => (clone $openTickets)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            ],
            'departmentPerformance' => Department::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'open' => Task::query()->where('department_id', $department->id)->whereNull('completed_at')->whereNull('archived_at')->count(),
                    'overdue' => Task::query()->where('department_id', $department->id)->whereNull('completed_at')->whereNull('archived_at')->where('due_at', '<', now())->count(),
                    'completed_week' => Task::query()->where('department_id', $department->id)->where('completed_at', '>=', now()->startOfWeek())->count(),
                ]),
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
                || $department->manager_id === $user->id,
            403,
        );

        $deptTasks = fn (): Builder => Task::query()
            ->where('department_id', $department->id)
            ->whereNull('completed_at')
            ->whereNull('archived_at');

        return Inertia::render('dashboard/department', [
            'department' => $department->only(['id', 'name']),
            'counts' => [
                'open' => $deptTasks()->count(),
                'unassigned' => $deptTasks()->whereNull('primary_assignee_id')->count(),
                'overdue' => $deptTasks()->where('due_at', '<', now())->count(),
                'blocked' => $deptTasks()->whereHas('column', fn ($q) => $q->where('semantic_status', 'blocked'))->count(),
                'awaiting_review' => $deptTasks()->whereHas('column', fn ($q) => $q->where('semantic_status', 'review'))->count(),
                'open_tickets' => Ticket::query()->where('department_id', $department->id)->whereIn('status', Ticket::OPEN_STATUSES)->count(),
            ],
            'workload' => User::query()
                ->where('department_id', $department->id)
                ->where('status', User::STATUS_ACTIVE)
                ->withCount(['assignedOpenTasks as open_tasks', 'assignedOverdueTasks as overdue_tasks'])
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
                ->where('department_id', $department->id)
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
            'myTickets' => Ticket::query()
                ->where('requester_id', $user->id)
                ->whereIn('status', Ticket::OPEN_STATUSES)
                ->with('category:id,name')
                ->latest()
                ->limit(5)
                ->get(['id', 'ticket_number', 'title', 'status', 'category_id', 'created_at']),
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

        return Inertia::render('dashboard/it', [
            'counts' => [
                'new' => Ticket::query()->where('status', Ticket::STATUS_NEW)->count(),
                'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
                'critical' => (clone $open)->where('priority', 'critical')->count(),
                'overdue' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
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
