<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = trim($request->string('q')->toString());

        $results = [
            'tasks' => [],
            'tickets' => [],
            'boards' => [],
            'users' => [],
        ];

        if (mb_strlen($query) >= 2) {
            // lower() + like is portable across PostgreSQL and sqlite.
            $lower = '%'.mb_strtolower(str_replace(['%', '_'], ['\%', '\_'], $query)).'%';
            $digits = ltrim(strtoupper($query), 'TK-');
            $number = ctype_digit($digits) ? (int) $digits : null;

            $results['tasks'] = Task::query()
                ->where(fn ($q) => $q->whereRaw('lower(title) like ?', [$lower])
                    ->orWhereRaw('lower(description) like ?', [$lower])
                    ->when($number !== null, fn ($qq) => $qq->orWhere('task_number', $number)))
                ->with(['board:id,name,visibility,department_id,is_active', 'assignee:id,name', 'column:id,name'])
                ->limit(50)
                ->get()
                ->filter(fn (Task $task) => $user->can('view', $task))
                ->take(15)
                ->values();

            $results['tickets'] = Ticket::query()
                ->when(! $user->can('tickets.manage'), fn ($q) => $q->where('requester_id', $user->id))
                ->where(fn ($q) => $q->whereRaw('lower(title) like ?', [$lower])
                    ->orWhereRaw('lower(description) like ?', [$lower])
                    ->when($number !== null, fn ($qq) => $qq->orWhere('ticket_number', $number)))
                ->with(['requester:id,name', 'category:id,name'])
                ->limit(15)
                ->get();

            $results['boards'] = Board::query()
                ->whereRaw('lower(name) like ?', [$lower])
                ->limit(20)
                ->get()
                ->filter(fn (Board $board) => $user->can('view', $board))
                ->take(10)
                ->values();

            if ($user->can('users.view')) {
                $results['users'] = User::query()
                    ->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$lower])->orWhereRaw('lower(email) like ?', [$lower]))
                    ->with('department:id,name')
                    ->limit(10)
                    ->get(['id', 'name', 'email', 'department_id', 'job_title']);
            }
        }

        return Inertia::render('search/index', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
