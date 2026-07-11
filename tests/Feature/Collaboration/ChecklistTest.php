<?php

namespace Tests\Feature\Collaboration;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(User $creator): Task
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        return Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'created_by' => $creator->id,
        ]);
    }

    public function test_task_owner_can_build_a_checklist_and_tick_items()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask($user);

        $this->actingAs($user)->post("/tasks/{$task->id}/checklists", ['name' => 'Launch steps'])->assertRedirect();

        $checklist = Checklist::query()->firstOrFail();

        $this->actingAs($user)->post("/checklists/{$checklist->id}/items", ['title' => 'Write copy'])->assertRedirect();

        $item = ChecklistItem::query()->firstOrFail();

        $this->actingAs($user)->patch("/checklist-items/{$item->id}", ['is_completed' => true])->assertRedirect();

        $item->refresh();
        $this->assertTrue($item->is_completed);
        $this->assertSame($user->id, $item->completed_by);
        $this->assertNotNull($item->completed_at);

        // Unticking clears the completion metadata.
        $this->actingAs($user)->patch("/checklist-items/{$item->id}", ['is_completed' => false]);
        $item->refresh();
        $this->assertNull($item->completed_by);
        $this->assertNull($item->completed_at);
    }

    public function test_unrelated_users_cannot_modify_checklists()
    {
        $owner = User::factory()->create()->assignRole('Employee');
        $stranger = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask($owner);

        $this->actingAs($stranger)
            ->post("/tasks/{$task->id}/checklists", ['name' => 'Nope'])
            ->assertForbidden();
    }
}
