<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private function boardWithColumns(): Board
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Backlog', 'position' => 1]);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Doing', 'position' => 2]);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Done', 'position' => 3, 'is_completion_column' => true]);

        return $board;
    }

    public function test_manager_can_bulk_reassign_tasks()
    {
        $manager = User::factory()->create()->assignRole('Department Manager');
        $newAssignee = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $column = $board->columns()->first();

        $tasks = Task::factory()->count(3)->create(['board_id' => $board->id, 'board_column_id' => $column->id, 'created_by' => $manager->id]);

        $this->actingAs($manager)
            ->post('/tasks/bulk-reassign', [
                'task_ids' => $tasks->pluck('id')->all(),
                'assignee_id' => $newAssignee->id,
            ])
            ->assertRedirect();

        foreach ($tasks as $task) {
            $this->assertSame($newAssignee->id, $task->refresh()->primary_assignee_id);
            $this->assertTrue($task->assignees()->where('user_id', $newAssignee->id)->exists());
        }
    }

    public function test_bulk_reassign_can_assign_someone_outside_the_boards_membership()
    {
        $manager = User::factory()->create()->assignRole('Department Manager');
        $outsider = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $board->members()->attach($manager->id, ['access_level' => 'manage']);

        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id, 'created_by' => $manager->id]);

        $this->actingAs($manager)
            ->post('/tasks/bulk-reassign', [
                'task_ids' => [$task->id],
                'assignee_id' => $outsider->id,
            ])
            ->assertRedirect();

        $this->assertSame($outsider->id, $task->refresh()->primary_assignee_id);
        $this->actingAs($outsider)->get("/boards/{$board->id}")->assertOk();
    }

    public function test_bulk_reassign_rejects_an_inactive_assignee()
    {
        $manager = User::factory()->create()->assignRole('Department Manager');
        $inactive = User::factory()->create(['status' => User::STATUS_INACTIVE])->assignRole('Employee');
        $board = $this->boardWithColumns();
        $column = $board->columns()->first();

        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id, 'created_by' => $manager->id]);

        $this->actingAs($manager)
            ->post('/tasks/bulk-reassign', [
                'task_ids' => [$task->id],
                'assignee_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('assignee_id');

        $this->assertNull($task->refresh()->primary_assignee_id);
    }

    public function test_bulk_reassign_fails_the_whole_batch_if_any_task_is_unauthorized()
    {
        $manager = User::factory()->create()->assignRole('Department Manager');
        $newAssignee = User::factory()->create()->assignRole('Employee');
        $ownBoard = $this->boardWithColumns();
        $ownColumn = $ownBoard->columns()->first();
        $ownTask = Task::factory()->create(['board_id' => $ownBoard->id, 'board_column_id' => $ownColumn->id, 'created_by' => $manager->id]);

        $restrictedBoard = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $restrictedColumn = BoardColumn::factory()->create(['board_id' => $restrictedBoard->id]);
        $restrictedTask = Task::factory()->create(['board_id' => $restrictedBoard->id, 'board_column_id' => $restrictedColumn->id]);

        $this->actingAs($manager)
            ->post('/tasks/bulk-reassign', [
                'task_ids' => [$ownTask->id, $restrictedTask->id],
                'assignee_id' => $newAssignee->id,
            ])
            ->assertForbidden();

        // Neither task should have been touched — the batch failed atomically.
        $this->assertNull($ownTask->refresh()->primary_assignee_id);
        $this->assertNull($restrictedTask->refresh()->primary_assignee_id);
    }

    public function test_manager_can_bulk_move_tasks_and_completion_follows()
    {
        $manager = User::factory()->create()->assignRole('Department Manager');
        $board = $this->boardWithColumns();
        [$backlog, , $done] = $board->columns()->get()->all();

        $tasks = Task::factory()->count(2)->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $manager->id]);

        $this->actingAs($manager)
            ->post('/tasks/bulk-move', [
                'task_ids' => $tasks->pluck('id')->all(),
                'board_column_id' => $done->id,
            ])
            ->assertRedirect();

        foreach ($tasks as $task) {
            $task->refresh();
            $this->assertSame($done->id, $task->board_column_id);
            $this->assertNotNull($task->completed_at);
        }
    }

    public function test_bulk_move_gives_each_task_a_distinct_incrementing_position()
    {
        $manager = User::factory()->create()->assignRole('Department Manager');
        $board = $this->boardWithColumns();
        [$backlog, $ready] = $board->columns()->get()->all();

        $existing = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $ready->id, 'position' => 1, 'created_by' => $manager->id]);
        $tasks = Task::factory()->count(3)->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $manager->id]);

        $this->actingAs($manager)
            ->post('/tasks/bulk-move', [
                'task_ids' => $tasks->pluck('id')->all(),
                'board_column_id' => $ready->id,
            ])
            ->assertRedirect();

        $positions = Task::query()
            ->where('board_column_id', $ready->id)
            ->pluck('position')
            ->all();

        $this->assertCount(4, $positions);
        $this->assertCount(4, array_unique($positions), 'every task in the column must have a distinct position');
        $this->assertContains($existing->fresh()->position, $positions);
    }

    public function test_bulk_move_rejects_tasks_from_a_different_board()
    {
        $manager = User::factory()->create()->assignRole('Department Manager');
        $board = $this->boardWithColumns();
        $otherBoard = $this->boardWithColumns();
        $done = $board->columns()->where('is_completion_column', true)->first();

        $task = Task::factory()->create([
            'board_id' => $otherBoard->id,
            'board_column_id' => $otherBoard->columns()->first()->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->post('/tasks/bulk-move', [
                'task_ids' => [$task->id],
                'board_column_id' => $done->id,
            ])
            ->assertSessionHasErrors('board_column_id');
    }
}
