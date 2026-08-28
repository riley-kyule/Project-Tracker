<?php

namespace Tests\Feature\Dashboards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Comment;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_my_counts()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create();
        $backlog = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog']);
        $blocked = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'blocked']);

        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'primary_assignee_id' => $user->id,
            'due_at' => now()->subDay(),
        ]);
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $blocked->id,
            'primary_assignee_id' => $user->id,
        ]);
        // Someone else's task is not counted.
        Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id]);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $counts = $response->viewData('page')['props']['counts'];

        $this->assertSame(2, $counts['open']);
        $this->assertSame(1, $counts['overdue']);
        $this->assertSame(1, $counts['blocked']);
    }

    public function test_waiting_on_me_lists_tasks_pending_my_review()
    {
        $reviewer = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'review']);

        $pending = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'approver_id' => $reviewer->id,
            'approval_status' => Task::APPROVAL_PENDING,
            'title' => 'Needs my sign-off',
        ]);
        // Already decided — must not show up as still "waiting".
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'approver_id' => $reviewer->id,
            'approval_status' => Task::APPROVAL_APPROVED,
        ]);

        $response = $this->actingAs($reviewer)->get('/dashboard')->assertOk();
        $waitingOnMe = collect($response->viewData('page')['props']['waitingOnMe']);

        $this->assertSame(1, $waitingOnMe->count());
        $this->assertSame($pending->id, $waitingOnMe->first()['id']);
    }

    public function test_mentions_are_listed_and_restricted_ones_are_hidden()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $author = User::factory()->create(['name' => 'Grace Hopper'])->assignRole('Employee');

        $visibleBoard = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $visibleColumn = BoardColumn::factory()->create(['board_id' => $visibleBoard->id]);
        $visibleTask = Task::factory()->create(['board_id' => $visibleBoard->id, 'board_column_id' => $visibleColumn->id, 'title' => 'Ship it']);

        $restrictedBoard = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $restrictedColumn = BoardColumn::factory()->create(['board_id' => $restrictedBoard->id]);
        $restrictedTask = Task::factory()->create(['board_id' => $restrictedBoard->id, 'board_column_id' => $restrictedColumn->id]);

        $visibleComment = Comment::factory()->create([
            'commentable_type' => Task::class,
            'commentable_id' => $visibleTask->id,
            'user_id' => $author->id,
            'body' => 'Can you take a look?',
        ]);
        $visibleComment->mentions()->create(['mentioned_user_id' => $user->id, 'notified_at' => now()]);

        $restrictedComment = Comment::factory()->create([
            'commentable_type' => Task::class,
            'commentable_id' => $restrictedTask->id,
            'user_id' => $author->id,
            'body' => 'Secret mention',
        ]);
        $restrictedComment->mentions()->create(['mentioned_user_id' => $user->id, 'notified_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $mentions = collect($response->viewData('page')['props']['mentions']);

        $this->assertSame(1, $mentions->count());
        $this->assertSame('Ship it', $mentions->first()['task_title']);
        $this->assertSame('Grace Hopper', $mentions->first()['author']);
    }

    public function test_quick_capture_boards_only_lists_boards_i_can_create_tasks_on()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $company = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        BoardColumn::factory()->create(['board_id' => $company->id, 'semantic_status' => 'backlog', 'position' => 1]);

        $restricted = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        BoardColumn::factory()->create(['board_id' => $restricted->id, 'semantic_status' => 'backlog']);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $boards = collect($response->viewData('page')['props']['quickCaptureBoards']);

        $this->assertTrue($boards->contains('id', $company->id));
        $this->assertFalse($boards->contains('id', $restricted->id));
    }

    public function test_quick_add_task_is_assigned_to_me_by_default()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog']);

        $this->actingAs($user)
            ->post("/boards/{$board->id}/tasks", [
                'title' => 'Quick captured task',
                'board_column_id' => $column->id,
                'priority' => 'medium',
                'primary_assignee_id' => $user->id,
            ])
            ->assertRedirect();

        $task = Task::query()->where('title', 'Quick captured task')->firstOrFail();
        $this->assertSame($user->id, $task->primary_assignee_id);
    }

    /**
     * An agent (e.g. IT) working the ticket queue is almost never the
     * requester of their own open tickets — they're the assignee. Scoping
     * "My open tickets" to requester_id alone left an agent's entire queue
     * invisible on their own dashboard.
     */
    public function test_my_open_tickets_includes_tickets_assigned_to_me_not_just_ones_i_raised()
    {
        $agent = User::factory()->create()->assignRole('Employee');

        $assignedToMe = Ticket::factory()->create([
            'status' => Ticket::STATUS_ASSIGNED,
            'assigned_to' => $agent->id,
        ]);

        $raisedByMe = Ticket::factory()->create([
            'status' => Ticket::STATUS_NEW,
            'requester_id' => $agent->id,
        ]);

        // Someone else's ticket, neither raised by nor assigned to this agent.
        Ticket::factory()->create(['status' => Ticket::STATUS_NEW]);

        // Closed tickets, even if assigned to this agent, aren't "open".
        Ticket::factory()->create([
            'status' => Ticket::STATUS_CLOSED,
            'assigned_to' => $agent->id,
        ]);

        $props = $this->actingAs($agent)->get('/dashboard')->assertOk()->viewData('page')['props'];
        $ticketIds = collect($props['myTickets'])->pluck('id');

        $this->assertTrue($ticketIds->contains($assignedToMe->id));
        $this->assertTrue($ticketIds->contains($raisedByMe->id));
        $this->assertCount(2, $ticketIds);
    }
}
