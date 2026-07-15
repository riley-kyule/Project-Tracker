<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\TaskRelation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskRelationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(Board $board): Task
    {
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        return Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);
    }

    public function test_linking_two_tasks_shows_up_on_both_sides()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $a = $this->makeTask($board);
        $b = $this->makeTask($board);

        $this->actingAs($admin)
            ->post("/tasks/{$a->id}/relations", ['related_task_id' => $b->id])
            ->assertRedirect();

        $this->assertDatabaseHas('task_relations', [
            'task_id' => min($a->id, $b->id),
            'related_task_id' => max($a->id, $b->id),
        ]);

        $detailA = $this->actingAs($admin)->get("/tasks/{$a->id}/detail")->json();
        $detailB = $this->actingAs($admin)->get("/tasks/{$b->id}/detail")->json();

        $this->assertSame($b->id, $detailA['relations'][0]['task']['id']);
        $this->assertSame($a->id, $detailB['relations'][0]['task']['id']);
    }

    public function test_linking_from_either_direction_does_not_create_a_duplicate_row()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $a = $this->makeTask($board);
        $b = $this->makeTask($board);

        $this->actingAs($admin)->post("/tasks/{$a->id}/relations", ['related_task_id' => $b->id]);
        $this->actingAs($admin)->post("/tasks/{$b->id}/relations", ['related_task_id' => $a->id]);

        $this->assertSame(1, TaskRelation::query()->count());
    }

    public function test_relation_is_removable_and_a_task_cannot_relate_to_itself()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $a = $this->makeTask($board);
        $b = $this->makeTask($board);

        $this->actingAs($admin)
            ->post("/tasks/{$a->id}/relations", ['related_task_id' => $a->id])
            ->assertSessionHasErrors('related_task_id');

        $this->actingAs($admin)->post("/tasks/{$a->id}/relations", ['related_task_id' => $b->id]);
        $relation = TaskRelation::query()->firstOrFail();

        $this->actingAs($admin)->delete("/task-relations/{$relation->id}")->assertRedirect();
        $this->assertDatabaseMissing('task_relations', ['id' => $relation->id]);
    }
}
