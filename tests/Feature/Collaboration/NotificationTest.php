<?php

namespace Tests\Feature\Collaboration;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_notifies_the_new_assignee()
    {
        Notification::fake();

        $manager = User::factory()->create()->assignRole('Administrator');
        $assignee = User::factory()->create()->assignRole('Employee');

        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        $this->actingAs($manager)->post("/boards/{$board->id}/tasks", [
            'title' => 'Assigned on create',
            'board_column_id' => $column->id,
            'priority' => 'medium',
            'primary_assignee_id' => $assignee->id,
        ]);

        Notification::assertSentTo($assignee, TaskAssigned::class);
    }

    public function test_reassignment_notifies_but_self_assignment_does_not()
    {
        Notification::fake();

        $admin = User::factory()->create()->assignRole('Administrator');
        $assignee = User::factory()->create()->assignRole('Employee');

        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);

        $this->actingAs($admin)->patch("/tasks/{$task->id}", ['primary_assignee_id' => $admin->id]);
        Notification::assertNotSentTo($admin, TaskAssigned::class);

        $this->actingAs($admin)->patch("/tasks/{$task->id}", ['primary_assignee_id' => $assignee->id]);
        Notification::assertSentTo($assignee, TaskAssigned::class);
    }

    public function test_notification_endpoints_list_and_mark_read()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $assignee = User::factory()->create()->assignRole('Employee');

        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);

        $this->actingAs($admin)->patch("/tasks/{$task->id}", ['primary_assignee_id' => $assignee->id]);

        $response = $this->actingAs($assignee)->get('/notifications')->assertOk();
        $this->assertSame(1, $response->json('unread_count'));

        $id = $response->json('notifications.0.id');
        $this->actingAs($assignee)->post("/notifications/{$id}/read")->assertOk();

        $this->assertSame(0, $this->actingAs($assignee)->get('/notifications')->json('unread_count'));

        // A user cannot mark someone else's notification.
        $this->actingAs($admin)->patch("/tasks/{$task->id}", ['primary_assignee_id' => null]);
        $this->actingAs($admin)->patch("/tasks/{$task->id}", ['primary_assignee_id' => $assignee->id]);
        $newId = $this->actingAs($assignee)->get('/notifications')->json('notifications.0.id');
        $this->actingAs($admin)->post("/notifications/{$newId}/read")->assertNotFound();
    }
}
