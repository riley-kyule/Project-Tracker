<?php

namespace Tests\Feature\Collaboration;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
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

    public function test_task_owner_can_rename_a_checklist()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask($user);
        $checklist = Checklist::factory()->create(['task_id' => $task->id, 'name' => 'Launch steps']);

        $this->actingAs($user)->patch("/checklists/{$checklist->id}", ['name' => 'Renamed steps'])->assertRedirect();

        $this->assertSame('Renamed steps', $checklist->refresh()->name);
    }

    public function test_task_owner_can_duplicate_a_checklist_with_its_items()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask($user);
        $checklist = Checklist::factory()->create(['task_id' => $task->id, 'name' => 'Launch steps']);
        ChecklistItem::factory()->create(['checklist_id' => $checklist->id, 'title' => 'Write copy', 'is_completed' => true]);

        $this->actingAs($user)->post("/checklists/{$checklist->id}/duplicate")->assertRedirect();

        $this->assertSame(2, Checklist::query()->count());
        $copy = Checklist::query()->where('id', '!=', $checklist->id)->firstOrFail();
        $this->assertSame('Launch steps (copy)', $copy->name);
        $this->assertSame('Write copy', $copy->items->first()->title);
        $this->assertFalse($copy->items->first()->is_completed);
    }

    public function test_task_owner_can_reorder_checklists()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask($user);
        $first = Checklist::factory()->create(['task_id' => $task->id, 'name' => 'First', 'position' => 1]);
        $second = Checklist::factory()->create(['task_id' => $task->id, 'name' => 'Second', 'position' => 2]);
        $third = Checklist::factory()->create(['task_id' => $task->id, 'name' => 'Third', 'position' => 3]);

        $this->actingAs($user)
            ->post("/tasks/{$task->id}/checklists/reorder", [
                'checklist_ids' => [$third->id, $first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $task->checklists()->pluck('id')->all(),
        );
    }

    public function test_checklist_reorder_rejects_ids_from_another_task()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask($user);
        $otherTask = $this->makeTask($user);
        $checklist = Checklist::factory()->create(['task_id' => $task->id]);
        $otherChecklist = Checklist::factory()->create(['task_id' => $otherTask->id]);

        $this->actingAs($user)
            ->post("/tasks/{$task->id}/checklists/reorder", [
                'checklist_ids' => [$checklist->id, $otherChecklist->id],
            ])
            ->assertStatus(422);

        $this->assertSame($otherTask->id, $otherChecklist->refresh()->task_id);
    }

    public function test_a_checklist_can_be_saved_as_a_reusable_template()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask($user);
        $checklist = Checklist::factory()->create(['task_id' => $task->id, 'name' => 'Launch steps']);
        ChecklistItem::factory()->create(['checklist_id' => $checklist->id, 'title' => 'Write copy', 'position' => 1]);
        ChecklistItem::factory()->create(['checklist_id' => $checklist->id, 'title' => 'Get sign-off', 'position' => 2]);

        $this->actingAs($user)
            ->post("/checklists/{$checklist->id}/save-as-template", ['name' => 'Launch checklist'])
            ->assertRedirect();

        $template = ChecklistTemplate::query()->firstOrFail();
        $this->assertSame('Launch checklist', $template->name);
        $this->assertSame($user->id, $template->created_by);
        $this->assertSame(['Write copy', 'Get sign-off'], $template->items);
    }

    public function test_a_template_can_be_applied_to_create_a_new_checklist()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask($user);
        $template = ChecklistTemplate::query()->create([
            'created_by' => $user->id,
            'name' => 'Onboarding',
            'items' => ['Provision laptop', 'Grant access', 'Schedule 1:1'],
        ]);

        $this->actingAs($user)
            ->post("/tasks/{$task->id}/checklists/from-template", ['template_id' => $template->id])
            ->assertRedirect();

        $checklist = Checklist::query()->where('task_id', $task->id)->firstOrFail();
        $this->assertSame('Onboarding', $checklist->name);
        $this->assertSame(['Provision laptop', 'Grant access', 'Schedule 1:1'], $checklist->items()->orderBy('position')->pluck('title')->all());
    }

    public function test_only_the_creator_or_an_administrator_can_delete_a_template()
    {
        $creator = User::factory()->create()->assignRole('Employee');
        $stranger = User::factory()->create()->assignRole('Employee');
        $admin = User::factory()->create()->assignRole('Administrator');
        $template = ChecklistTemplate::query()->create(['created_by' => $creator->id, 'name' => 'Mine', 'items' => ['Step']]);

        $this->actingAs($stranger)->delete("/checklist-templates/{$template->id}")->assertForbidden();
        $this->assertModelExists($template);

        $this->actingAs($admin)->delete("/checklist-templates/{$template->id}")->assertRedirect();
        $this->assertModelMissing($template);
    }

    public function test_unrelated_users_cannot_apply_a_template_to_a_task_they_cannot_edit()
    {
        $owner = User::factory()->create()->assignRole('Employee');
        $stranger = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask($owner);
        $template = ChecklistTemplate::query()->create(['created_by' => $owner->id, 'name' => 'Steps', 'items' => ['A']]);

        $this->actingAs($stranger)
            ->post("/tasks/{$task->id}/checklists/from-template", ['template_id' => $template->id])
            ->assertForbidden();
    }
}
