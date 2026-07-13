<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private function boardWithColumns(): Board
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Backlog', 'slug' => 'backlog', 'position' => 1]);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Doing', 'slug' => 'doing', 'position' => 2]);
        BoardColumn::factory()->create([
            'board_id' => $board->id,
            'name' => 'Done',
            'slug' => 'done',
            'position' => 3,
            'is_completion_column' => true,
        ]);

        return $board;
    }

    public function test_employee_can_create_a_task_with_minimal_fields()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $column = $board->columns()->first();

        $this->actingAs($user)
            ->post("/boards/{$board->id}/tasks", [
                'title' => 'Write launch checklist',
                'board_column_id' => $column->id,
                'priority' => 'high',
            ])
            ->assertRedirect();

        $task = Task::query()->where('title', 'Write launch checklist')->firstOrFail();
        $this->assertSame($task->id, $task->task_number);
        $this->assertSame(1, $task->position);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Task::class,
            'auditable_id' => $task->id,
            'event' => 'created',
            'actor_id' => $user->id,
        ]);
    }

    public function test_viewer_cannot_create_tasks()
    {
        $viewer = User::factory()->create()->assignRole('Viewer');
        $board = $this->boardWithColumns();

        $this->actingAs($viewer)
            ->post("/boards/{$board->id}/tasks", [
                'title' => 'Nope',
                'board_column_id' => $board->columns()->first()->id,
                'priority' => 'low',
            ])
            ->assertForbidden();
    }

    public function test_unrelated_employee_cannot_edit_someone_elses_task()
    {
        $author = User::factory()->create()->assignRole('Employee');
        $bystander = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $board->columns()->first()->id,
            'created_by' => $author->id,
        ]);

        $this->actingAs($bystander)
            ->patch("/tasks/{$task->id}", ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($author)
            ->patch("/tasks/{$task->id}", ['title' => 'Refined title'])
            ->assertRedirect();

        $this->assertSame('Refined title', $task->refresh()->title);
    }

    public function test_moving_a_task_persists_order_and_completion()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        [$backlog, , $done] = $board->columns()->get()->all();

        $first = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'position' => 1,
            'created_by' => $user->id,
        ]);
        $second = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'position' => 2,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post("/tasks/{$first->id}/move", ['board_column_id' => $done->id, 'position' => 1])
            ->assertRedirect();

        $first->refresh();
        $second->refresh();

        $this->assertSame($done->id, $first->board_column_id);
        $this->assertNotNull($first->completed_at);
        $this->assertSame(1, $second->position);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Task::class,
            'auditable_id' => $first->id,
            'event' => 'moved',
        ]);

        // Moving back out of the completion column clears completion.
        $this->actingAs($user)
            ->post("/tasks/{$first->id}/move", ['board_column_id' => $backlog->id, 'position' => 1]);

        $this->assertNull($first->refresh()->completed_at);
    }

    public function test_only_ceo_or_admin_can_flag_ceo_priority()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $board->columns()->first()->id,
            'created_by' => $employee->id,
        ]);

        $this->actingAs($employee)->patch("/tasks/{$task->id}", ['ceo_priority' => true]);
        $this->assertFalse($task->refresh()->ceo_priority);

        $ceo = User::factory()->create()->assignRole('CEO');
        $this->actingAs($ceo)->patch("/tasks/{$task->id}", ['ceo_priority' => true]);
        $this->assertTrue($task->refresh()->ceo_priority);
    }

    public function test_task_cannot_be_assigned_to_someone_without_board_access()
    {
        $member = User::factory()->create()->assignRole('Employee');
        $outsider = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $board->members()->attach($member->id, ['access_level' => 'contribute']);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        $this->actingAs($member)
            ->from("/boards/{$board->id}")
            ->post("/boards/{$board->id}/tasks", [
                'title' => 'Sensitive work',
                'board_column_id' => $column->id,
                'priority' => 'medium',
                'primary_assignee_id' => $outsider->id,
            ])
            ->assertSessionHasErrors('primary_assignee_id');

        $this->assertDatabaseMissing('tasks', ['title' => 'Sensitive work']);
    }
}
