<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ResetRecurringTask;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\CompanySetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskRenewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResetRecurringTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CompanySetting::current()->update(['timezone' => 'Africa/Nairobi']);
    }

    public function test_it_moves_a_due_task_to_the_ready_column_and_notifies_the_assignee()
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-08-05 07:00:00', 'Africa/Nairobi'));

        $assignee = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create();
        BoardColumn::factory()->create(['board_id' => $board->id, 'position' => 1, 'semantic_status' => 'backlog']);
        $ready = BoardColumn::factory()->create(['board_id' => $board->id, 'position' => 2, 'semantic_status' => 'ready']);
        $active = BoardColumn::factory()->create(['board_id' => $board->id, 'position' => 3, 'semantic_status' => 'active']);

        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $active->id,
            'primary_assignee_id' => $assignee->id,
            'auto_reset_frequency' => 'daily',
            'completed_at' => now()->subDay(),
        ]);

        ResetRecurringTask::dispatchSync($task->id);

        $task->refresh();
        $this->assertSame($ready->id, $task->board_column_id);
        $this->assertNotNull($task->last_auto_reset_at);
        // Landing outside the completion column reads as reopened, same as any other move off it.
        $this->assertNull($task->completed_at);
        Notification::assertSentTo($assignee, TaskRenewed::class);

        // Sanity check the "whatever column it was in" framing: it really did move.
        $this->assertDatabaseMissing('tasks', ['id' => $task->id, 'board_column_id' => $active->id]);
    }

    public function test_it_falls_back_to_the_first_column_when_the_board_has_no_ready_column()
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-08-05 07:00:00', 'Africa/Nairobi'));

        $board = Board::factory()->create();
        $first = BoardColumn::factory()->create(['board_id' => $board->id, 'position' => 1, 'semantic_status' => 'idea']);
        $second = BoardColumn::factory()->create(['board_id' => $board->id, 'position' => 2, 'semantic_status' => 'active']);

        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $second->id,
            'auto_reset_frequency' => 'daily',
        ]);

        ResetRecurringTask::dispatchSync($task->id);

        $this->assertSame($first->id, $task->refresh()->board_column_id);
    }

    public function test_it_does_nothing_if_not_actually_due()
    {
        $this->travelTo(Carbon::parse('2026-08-05 07:00:00', 'Africa/Nairobi'));

        $board = Board::factory()->create();
        BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'ready']);
        $active = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'active']);

        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $active->id,
            'auto_reset_frequency' => 'daily',
            'last_auto_reset_at' => now(),
        ]);

        ResetRecurringTask::dispatchSync($task->id);

        $this->assertSame($active->id, $task->refresh()->board_column_id);
    }
}
