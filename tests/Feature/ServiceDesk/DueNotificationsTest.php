<?php

namespace Tests\Feature\ServiceDesk;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DueNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_task_and_ticket_notify_assignees_once()
    {
        $assignee = User::factory()->create()->assignRole('Employee');
        $tech = User::factory()->create()->assignRole('IT Technician');

        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'primary_assignee_id' => $assignee->id,
            'due_at' => now()->subHours(2),
        ]);

        Ticket::factory()->create([
            'category_id' => TicketCategory::query()->firstOrFail()->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
            'assigned_to' => $tech->id,
            'due_at' => now()->subHour(),
        ]);

        $this->artisan('ewms:send-due-notifications')->assertSuccessful();

        $this->assertSame(1, $assignee->notifications()->where('data->type', 'task_overdue')->count());
        $this->assertSame(1, $tech->notifications()->where('data->type', 'ticket_overdue')->count());

        // Second run within the dedup window sends nothing new.
        $this->artisan('ewms:send-due-notifications')->assertSuccessful();

        $this->assertSame(1, $assignee->notifications()->count());
        $this->assertSame(1, $tech->notifications()->count());
    }

    public function test_tasks_due_within_a_day_get_due_soon_notice()
    {
        $assignee = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'primary_assignee_id' => $assignee->id,
            'due_at' => now()->addHours(6),
        ]);
        // Far-future tasks stay quiet.
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'primary_assignee_id' => $assignee->id,
            'due_at' => now()->addDays(5),
        ]);

        $this->artisan('ewms:send-due-notifications')->assertSuccessful();

        $this->assertSame(1, $assignee->notifications()->count());
        $this->assertSame('task_due_soon', $assignee->notifications()->first()->data['type']);
    }
}
