<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardColumnManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_manager_can_manage_their_own_board_columns()
    {
        $department = Department::query()->where('slug', 'seo')->firstOrFail();
        $manager = User::factory()->create(['department_id' => $department->id])->assignRole('Department Manager');
        $board = Board::factory()->create(['department_id' => $department->id]);

        $this->actingAs($manager)
            ->post("/boards/{$board->id}/columns", ['name' => 'Triage', 'semantic_status' => 'backlog'])
            ->assertRedirect();

        $column = BoardColumn::query()->where('board_id', $board->id)->where('name', 'Triage')->firstOrFail();
        $this->assertSame('triage', $column->slug);
        $this->assertFalse($column->is_completion_column);

        $this->actingAs($manager)
            ->patch("/board-columns/{$column->id}", ['name' => 'Triage queue', 'semantic_status' => 'completed'])
            ->assertRedirect();

        $column->refresh();
        $this->assertSame('Triage queue', $column->name);
        $this->assertTrue($column->is_completion_column);
    }

    public function test_manager_cannot_manage_another_departments_board()
    {
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $it = Department::query()->where('slug', 'it')->firstOrFail();
        $manager = User::factory()->create(['department_id' => $seo->id])->assignRole('Department Manager');
        $board = Board::factory()->create(['department_id' => $it->id]);

        $this->actingAs($manager)
            ->post("/boards/{$board->id}/columns", ['name' => 'Triage', 'semantic_status' => 'backlog'])
            ->assertForbidden();
    }

    public function test_deleting_a_column_with_tasks_is_blocked_until_emptied()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);

        $this->actingAs($admin)->delete("/board-columns/{$column->id}")->assertSessionHasErrors('column');
        $this->assertDatabaseHas('board_columns', ['id' => $column->id]);

        $task->delete();

        $this->actingAs($admin)->delete("/board-columns/{$column->id}")->assertRedirect();
        $this->assertDatabaseMissing('board_columns', ['id' => $column->id]);
    }

    public function test_reordering_persists_new_positions()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = Board::factory()->create();
        $first = BoardColumn::factory()->create(['board_id' => $board->id, 'position' => 1]);
        $second = BoardColumn::factory()->create(['board_id' => $board->id, 'position' => 2]);

        $this->actingAs($admin)
            ->post("/boards/{$board->id}/reorder-columns", ['column_ids' => [$second->id, $first->id]])
            ->assertRedirect();

        $this->assertSame(1, $second->refresh()->position);
        $this->assertSame(2, $first->refresh()->position);
    }

    public function test_new_column_name_colliding_with_an_existing_slug_is_de_duplicated()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = Board::factory()->create();
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Triage', 'slug' => 'triage']);

        $this->actingAs($admin)
            ->post("/boards/{$board->id}/columns", ['name' => 'Triage', 'semantic_status' => 'backlog'])
            ->assertRedirect();

        $this->assertDatabaseHas('board_columns', ['board_id' => $board->id, 'name' => 'Triage', 'slug' => 'triage-2']);
    }
}
