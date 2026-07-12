<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
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
