<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskBlocked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskBlockedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_a_task_into_a_blocked_column_notifies_the_assignee()
    {
        Notification::fake();

        $manager = User::factory()->create()->assignRole('Administrator');
        $assignee = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $backlog = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog', 'position' => 1]);
        $blocked = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'blocked', 'position' => 2]);
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'primary_assignee_id' => $assignee->id,
        ]);

        $this->actingAs($manager)
            ->post("/tasks/{$task->id}/move", ['board_column_id' => $blocked->id, 'position' => 1])
            ->assertRedirect();

        Notification::assertSentTo($assignee, TaskBlocked::class);
    }

    public function test_moving_between_two_non_blocked_columns_does_not_notify()
    {
        Notification::fake();

        $manager = User::factory()->create()->assignRole('Administrator');
        $assignee = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $backlog = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog', 'position' => 1]);
        $ready = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'ready', 'position' => 2]);
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'primary_assignee_id' => $assignee->id,
        ]);

        $this->actingAs($manager)
            ->post("/tasks/{$task->id}/move", ['board_column_id' => $ready->id, 'position' => 1])
            ->assertRedirect();

        Notification::assertNotSentTo($assignee, TaskBlocked::class);
    }

    public function test_moving_within_the_blocked_column_does_not_renotify()
    {
        Notification::fake();

        $manager = User::factory()->create()->assignRole('Administrator');
        $assignee = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $blocked = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'blocked', 'position' => 1]);
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $blocked->id,
            'position' => 1,
            'primary_assignee_id' => $assignee->id,
        ]);
        Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $blocked->id, 'position' => 2]);

        $this->actingAs($manager)
            ->post("/tasks/{$task->id}/move", ['board_column_id' => $blocked->id, 'position' => 2])
            ->assertRedirect();

        Notification::assertNotSentTo($assignee, TaskBlocked::class);
    }
}
