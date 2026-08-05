<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Label;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function boardWithColumn(): array
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        return [$board, $column];
    }

    public function test_duplicating_a_task_copies_its_structure_but_not_its_state()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $assignee = User::factory()->create()->assignRole('Employee');
        [$board, $column] = $this->boardWithColumn();
        $label = Label::factory()->create();

        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'created_by' => $user->id,
            'title' => 'Ship the redesign',
            'description' => 'Do the thing',
            'priority' => 'high',
            'primary_assignee_id' => $assignee->id,
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);
        $task->labels()->attach($label->id);

        $checklist = Checklist::factory()->create(['task_id' => $task->id, 'name' => 'Steps']);
        ChecklistItem::factory()->create(['checklist_id' => $checklist->id, 'title' => 'Write copy', 'is_completed' => true]);

        $this->actingAs($user)->post("/tasks/{$task->id}/duplicate")->assertRedirect();

        $this->assertSame(2, Task::query()->count());
        $copy = Task::query()->where('id', '!=', $task->id)->firstOrFail();

        $this->assertSame('Ship the redesign (copy)', $copy->title);
        $this->assertSame('Do the thing', $copy->description);
        $this->assertSame('high', $copy->priority);
        $this->assertSame($assignee->id, $copy->primary_assignee_id);
        $this->assertTrue($copy->assignees()->where('user_id', $assignee->id)->exists());
        $this->assertTrue($copy->labels()->where('labels.id', $label->id)->exists());

        // State does not carry over.
        $this->assertNull($copy->completed_at);
        $this->assertSame(0, $copy->progress_percentage);

        $copyChecklist = $copy->checklists()->firstOrFail();
        $this->assertSame('Steps', $copyChecklist->name);
        $copyItem = $copyChecklist->items()->firstOrFail();
        $this->assertSame('Write copy', $copyItem->title);
        $this->assertFalse($copyItem->is_completed);
    }

    public function test_duplicating_a_task_lands_in_the_same_column()
    {
        $user = User::factory()->create()->assignRole('Employee');
        [$board, $column] = $this->boardWithColumn();

        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/tasks/{$task->id}/duplicate")->assertRedirect();

        $copy = Task::query()->where('id', '!=', $task->id)->firstOrFail();
        $this->assertSame($column->id, $copy->board_column_id);
    }

    public function test_an_employee_without_create_access_cannot_duplicate_a_task()
    {
        $viewer = User::factory()->create()->assignRole('Viewer');
        [$board, $column] = $this->boardWithColumn();

        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);

        $this->actingAs($viewer)->post("/tasks/{$task->id}/duplicate")->assertForbidden();
        $this->assertSame(1, Task::query()->count());
    }
}
