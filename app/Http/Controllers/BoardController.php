<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\BoardRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\Label;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    /** Default workflow from the PRD; admins can reshape per board later. */
    public const DEFAULT_COLUMNS = [
        ['name' => 'Ideas', 'semantic_status' => 'idea'],
        ['name' => 'Backlog', 'semantic_status' => 'backlog'],
        ['name' => 'Ready', 'semantic_status' => 'ready'],
        ['name' => 'In Progress', 'semantic_status' => 'active'],
        ['name' => 'Blocked', 'semantic_status' => 'blocked'],
        ['name' => 'Awaiting Review', 'semantic_status' => 'review'],
        ['name' => 'Completed', 'semantic_status' => 'completed', 'is_completion_column' => true],
        ['name' => 'Archived', 'semantic_status' => 'archived', 'is_archive_column' => true],
    ];

    public function index(Request $request): Response
    {
        $boards = Board::query()
            ->with('department:id,name')
            ->withCount('tasks')
            ->orderBy('name')
            ->get()
            ->filter(fn (Board $board) => $request->user()->can('view', $board))
            ->values();

        return Inertia::render('boards/index', [
            'boards' => $boards,
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'canCreate' => $request->user()->can('create', Board::class),
            'canDelete' => $request->user()->hasRole('Administrator'),
        ]);
    }

    public function show(Request $request, Board $board): Response
    {
        Gate::authorize('view', $board);

        $board->load([
            'department:id,name',
            'columns',
            'columns.tasks' => fn ($query) => $query
                ->with(['assignee:id,name', 'labels:id,name,color'])
                ->withCount([
                    'dependencies as unresolved_dependencies_count' => fn ($q) => $q
                        ->whereNull('overridden_at')
                        ->whereHas('predecessor', fn ($qq) => $qq->whereNull('completed_at')),
                    'checklistItems as checklist_items_count',
                    'checklistItems as completed_checklist_items_count' => fn ($q) => $q->where('is_completed', true),
                ]),
            // No archived_at exclusion here: a task's board_column_id only ever
            // points at the archive column once archived_at is set, so this lets
            // archived cards show up there instead of vanishing everywhere.
        ]);

        // Confidential tasks the viewer has no explicit access to must not
        // appear as cards at all, not just be blocked at the detail endpoint.
        // Display order is date-driven rather than the manual drag position:
        // completion/archive columns read newest-first (most recently
        // finished at the top), everything else reads soonest-due-first.
        $board->columns->each(function (BoardColumn $column) use ($request) {
            $visible = $column->tasks->filter(fn (Task $task) => Gate::forUser($request->user())->allows('view', $task));

            $sorted = match (true) {
                $column->is_completion_column => $visible->sortByDesc(fn (Task $task) => $task->completed_at ?? $task->created_at),
                $column->is_archive_column => $visible->sortByDesc(fn (Task $task) => $task->archived_at ?? $task->created_at),
                default => $visible->sortBy(fn (Task $task) => $task->due_at ?? Carbon::create(9999, 12, 31)),
            };

            $column->setRelation('tasks', $sorted->values());
        });

        return Inertia::render('boards/show', [
            'board' => $board,
            'boardTaskOptions' => Task::query()
                ->where('board_id', $board->id)
                ->whereNull('archived_at')
                ->orderBy('title')
                ->get(['id', 'title', 'task_number', 'confidentiality'])
                ->filter(fn (Task $task) => Gate::forUser($request->user())->allows('view', $task))
                ->values(),
            'members' => User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->filter(fn (User $user) => Gate::forUser($user)->allows('view', $board))
                ->values(),
            // Deliberately not board-scoped: adding someone as a task collaborator
            // is how a person outside this board's department gets access at all,
            // so the picker for that action needs to offer every active user.
            'allMembers' => User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']),
            'labels' => Label::query()->orderBy('name')->get(),
            'can' => [
                'manage' => $request->user()->can('manage', $board),
                'createTask' => $request->user()->can('create', [Task::class, $board]),
                'flagCeoPriority' => $request->user()->hasAnyRole(['CEO', 'Administrator']),
                'delete' => $request->user()->can('delete', $board),
            ],
        ]);
    }

    public function store(BoardRequest $request): RedirectResponse
    {
        Gate::authorize('create', Board::class);

        $board = DB::transaction(function () use ($request) {
            $board = Board::create([
                ...$request->validated(),
                'created_by' => $request->user()->id,
            ]);

            foreach (self::DEFAULT_COLUMNS as $index => $column) {
                $board->columns()->create([
                    ...$column,
                    'slug' => str($column['name'])->slug(),
                    'position' => $index + 1,
                ]);
            }

            AuditLogger::log($board, 'created', [], $request->validated());

            return $board;
        });

        return redirect()->route('boards.show', $board);
    }

    public function update(BoardRequest $request, Board $board): RedirectResponse
    {
        Gate::authorize('update', $board);

        $old = $board->only(array_keys($request->validated()));
        $board->update($request->validated());
        AuditLogger::log($board, 'updated', $old, $request->validated());

        return back();
    }

    public function destroy(Board $board): RedirectResponse
    {
        Gate::authorize('delete', $board);

        AuditLogger::log($board, 'deleted', ['name' => $board->name], []);
        $board->delete();

        return redirect()->route('boards.index')->with('success', 'Board deleted.');
    }
}
