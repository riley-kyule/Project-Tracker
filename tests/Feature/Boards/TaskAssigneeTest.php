<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssigneeTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(): Task
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        return Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);
    }

    public function test_setting_primary_assignee_syncs_task_assignees()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $assignee = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($admin)->patch("/tasks/{$task->id}", ['primary_assignee_id' => $assignee->id]);

        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $assignee->id, 'assignment_type' => 'assignee']);
    }

    public function test_reassigning_removes_the_old_assignee_row()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $first = User::factory()->create()->assignRole('Employee');
        $second = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($admin)->patch("/tasks/{$task->id}", ['primary_assignee_id' => $first->id]);
        $this->actingAs($admin)->patch("/tasks/{$task->id}", ['primary_assignee_id' => $second->id]);

        $this->assertDatabaseMissing('task_assignees', ['task_id' => $task->id, 'user_id' => $first->id]);
        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $second->id, 'assignment_type' => 'assignee']);
    }

    public function test_adding_a_collaborator_grants_update_access()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $collaborator = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($admin)
            ->post("/tasks/{$task->id}/assignees", ['user_id' => $collaborator->id, 'assignment_type' => 'collaborator'])
            ->assertRedirect();

        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $collaborator->id, 'assignment_type' => 'collaborator']);

        $this->actingAs($collaborator)
            ->patch("/tasks/{$task->id}", ['title' => 'Updated by collaborator'])
            ->assertRedirect();

        $this->assertSame('Updated by collaborator', $task->refresh()->title);
    }

    public function test_a_watcher_cannot_update_the_task()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $watcher = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($admin)->post("/tasks/{$task->id}/assignees", ['user_id' => $watcher->id, 'assignment_type' => 'watcher']);

        $this->actingAs($watcher)->patch("/tasks/{$task->id}", ['title' => 'Should be forbidden'])->assertForbidden();
    }

    public function test_removing_a_collaborator_works_but_cannot_remove_the_primary_assignee()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $collaborator = User::factory()->create()->assignRole('Employee');
        $assignee = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($admin)->patch("/tasks/{$task->id}", ['primary_assignee_id' => $assignee->id]);
        $this->actingAs($admin)->post("/tasks/{$task->id}/assignees", ['user_id' => $collaborator->id, 'assignment_type' => 'collaborator']);

        $this->actingAs($admin)->delete("/tasks/{$task->id}/assignees/{$collaborator->id}")->assertRedirect();
        $this->assertDatabaseMissing('task_assignees', ['task_id' => $task->id, 'user_id' => $collaborator->id]);

        $this->actingAs($admin)->delete("/tasks/{$task->id}/assignees/{$assignee->id}")->assertNotFound();
        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $assignee->id, 'assignment_type' => 'assignee']);
    }

    public function test_ticket_conversion_keeps_the_invariant()
    {
        $tech = User::factory()->create()->assignRole('IT Technician');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $ticket = Ticket::factory()->create([
            'category_id' => TicketCategory::query()->firstOrFail()->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
            'assigned_to' => $tech->id,
        ]);

        $this->actingAs($tech)->post("/tickets/{$ticket->id}/convert-to-task", [
            'board_id' => $board->id,
            'board_column_id' => $column->id,
        ]);

        $task = Task::query()->firstOrFail();
        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $tech->id, 'assignment_type' => 'assignee']);
    }
}
