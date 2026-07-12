<?php

namespace Tests\Feature\Reports;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_visible_tasks_and_hides_restricted_ones()
    {
        $user = User::factory()->create()->assignRole('Employee');

        $open = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $openColumn = BoardColumn::factory()->create(['board_id' => $open->id]);
        Task::factory()->create(['board_id' => $open->id, 'board_column_id' => $openColumn->id, 'title' => 'Migration plan alpha']);

        $secret = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $secretColumn = BoardColumn::factory()->create(['board_id' => $secret->id]);
        Task::factory()->create(['board_id' => $secret->id, 'board_column_id' => $secretColumn->id, 'title' => 'Migration plan omega']);

        $response = $this->actingAs($user)->get('/search?q=migration+plan')->assertOk();
        $titles = collect($response->viewData('page')['props']['results']['tasks'])->pluck('title');

        $this->assertTrue($titles->contains('Migration plan alpha'));
        $this->assertFalse($titles->contains('Migration plan omega'));
    }

    public function test_search_finds_task_by_number()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);
        $task->forceFill(['task_number' => $task->id])->save();

        $response = $this->actingAs($user)->get("/search?q=T-{$task->id}")->assertOk();
        $numbers = collect($response->viewData('page')['props']['results']['tasks'])->pluck('task_number');

        $this->assertTrue($numbers->contains($task->id));
    }

    public function test_people_results_require_users_view_permission()
    {
        User::factory()->create(['name' => 'Zebulon Findable'])->assignRole('Employee');

        $employee = User::factory()->create()->assignRole('Employee');
        $response = $this->actingAs($employee)->get('/search?q=Zebulon')->assertOk();
        $this->assertCount(0, $response->viewData('page')['props']['results']['users']);

        $admin = User::factory()->create()->assignRole('Administrator');
        $response = $this->actingAs($admin)->get('/search?q=Zebulon')->assertOk();
        $this->assertCount(1, $response->viewData('page')['props']['results']['users']);
    }
}
