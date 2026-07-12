<?php

namespace Tests\Feature\Dashboards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_my_counts()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create();
        $backlog = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog']);
        $blocked = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'blocked']);

        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'primary_assignee_id' => $user->id,
            'due_at' => now()->subDay(),
        ]);
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $blocked->id,
            'primary_assignee_id' => $user->id,
        ]);
        // Someone else's task is not counted.
        Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id]);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $counts = $response->viewData('page')['props']['counts'];

        $this->assertSame(2, $counts['open']);
        $this->assertSame(1, $counts['overdue']);
        $this->assertSame(1, $counts['blocked']);
    }
}
