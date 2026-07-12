<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
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
