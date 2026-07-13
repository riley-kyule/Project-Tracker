<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Notifications\TicketUpdated;
use App\Services\AuditLogger;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $manager = $request->user()->can('tickets.manage');

        $tickets = Ticket::query()
            ->with(['requester:id,name', 'assignee:id,name', 'category:id,name'])
            ->when(! $manager, fn ($query) => $query->where('requester_id', $request->user()->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('assigned'), fn ($query) => $request->string('assigned')->toString() === 'unassigned'
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $request->integer('assigned')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('tickets/index', [
            'tickets' => $tickets,
            'categories' => TicketCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'isManager' => $manager,
            'filters' => $request->only(['status', 'priority', 'assigned']),
        ]);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $manager = $request->user()->can('tickets.manage');

        $ticket->load([
            'requester:id,name,email',
            'assignee:id,name',
            'category:id,name',
            'department:id,name',
            'convertedTask:id,task_number,title,board_id',
            'statusHistory.changedBy:id,name',
        ]);

        return Inertia::render('tickets/show', [
            'ticket' => $ticket,
            'comments' => $ticket->comments()
                ->whereNull('parent_id')
                ->when(! $manager, fn ($query) => $query->where('is_internal', false))
                ->with(['user:id,name', 'replies.user:id,name'])
                ->oldest()
                ->get(),
            'attachments' => $ticket->attachments()->with('uploader:id,name')->latest()->get(),
            'technicians' => $manager
                ? User::query()->permission('tickets.manage')->where('status', User::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name'])
                : [],
            'boards' => $manager
                ? Board::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->with('columns:id,board_id,name')
                    ->get(['id', 'name', 'visibility', 'department_id'])
                    ->filter(fn (Board $board) => $request->user()->can('create', [Task::class, $board]))
                    ->values()
                : [],
            'isManager' => $manager,
            'allowedTransitions' => $manager ? (Ticket::TRANSITIONS[$ticket->status] ?? []) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Ticket::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
            'category_id' => ['required', Rule::exists('ticket_categories', 'id')->where('is_active', true)],
            'impact' => ['required', Rule::in(['low', 'medium', 'high'])],
        ]);

        $category = TicketCategory::query()->findOrFail($validated['category_id']);
        $validated['priority'] = $category->default_priority ?? 'medium';

        $ticket = TicketService::submit($request->user(), $validated);

        return redirect()->route('tickets.show', $ticket);
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('manage', $ticket);

        $validated = $request->validate(['assigned_to' => ['required', 'integer', 'exists:users,id']]);

        $technician = User::query()->findOrFail($validated['assigned_to']);
        abort_unless(
            $technician->isActive() && $technician->can('tickets.manage'),
            422,
            'Assignee must be an active service desk technician.',
        );

        TicketService::assign($ticket, $technician, $request->user());

        return back();
    }

    public function transition(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('manage', $ticket);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        TicketService::transition($ticket, $validated['status'], $request->user(), $validated['reason'] ?? null);

        return back();
    }

    public function resolve(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('manage', $ticket);

        $validated = $request->validate([
            'resolution_method' => ['required', Rule::in(Ticket::RESOLUTION_METHODS)],
            'resolution_summary' => ['required', 'string', 'max:5000'],
            'time_spent_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ]);

        TicketService::resolve(
            $ticket,
            $request->user(),
            $validated['resolution_method'],
            $validated['resolution_summary'],
            $validated['time_spent_minutes'],
        );

        return back();
    }

    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket); // Requesters may reopen their own tickets.

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        TicketService::reopen($ticket, $request->user(), $validated['reason'] ?? null);

        return back();
    }

    public function convertToTask(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('manage', $ticket);

        $validated = $request->validate([
            'board_id' => ['required', 'integer', 'exists:boards,id'],
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'primary_assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        $board = Board::query()->findOrFail($validated['board_id']);
        Gate::authorize('create', [Task::class, $board]);
        abort_unless(
            $board->columns()->whereKey($validated['board_column_id'])->exists(),
            422,
            'Column does not belong to the selected board.',
        );

        if (($validated['primary_assignee_id'] ?? null) !== null) {
            $assignee = User::query()->findOrFail($validated['primary_assignee_id']);
            abort_unless(
                $assignee->isActive() && Gate::forUser($assignee)->allows('view', $board),
                422,
                'Assignee must be active and able to access the selected board.',
            );
        }

        $task = TicketService::convertToTask($ticket, $request->user(), $board, $validated['board_column_id'], $validated);

        return redirect()->route('boards.show', $task->board_id);
    }

    public function comment(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $isInternal = $request->boolean('is_internal') && $request->user()->can('tickets.manage');

        $comment = $ticket->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_internal' => $isInternal,
        ]);

        // A technician's first public reply counts as first response.
        if ($request->user()->can('tickets.manage') && ! $isInternal && $ticket->first_responded_at === null) {
            $ticket->forceFill(['first_responded_at' => now()])->save();
        }

        AuditLogger::log($ticket, $isInternal ? 'internal_note_added' : 'commented', [], ['comment_id' => $comment->id]);

        if (! $isInternal && $request->user()->id !== $ticket->requester_id && $request->user()->can('tickets.manage')) {
            $ticket->requester?->notify(new TicketUpdated($ticket));
        }

        return back();
    }
}
