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

        $firstCompletedAt = $first->refresh()->first_completed_at;
        $this->assertNotNull($firstCompletedAt);

        // Moving back out of the completion column clears completion but
        // keeps a permanent record that it was reopened.
        $this->actingAs($user)
            ->post("/tasks/{$first->id}/move", ['board_column_id' => $backlog->id, 'position' => 1]);

        $first->refresh();
        $this->assertNull($first->completed_at);
        $this->assertNotNull($first->reopened_at);
        $this->assertSame($firstCompletedAt->toDateTimeString(), $first->first_completed_at->toDateTimeString());
    }

    public function test_completion_note_is_captured_when_a_task_completes()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        [$backlog, , $done] = $board->columns()->get()->all();

        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post("/tasks/{$task->id}/move", [
                'board_column_id' => $done->id,
                'position' => 1,
                'completion_note' => 'Shipped the redesign, QA signed off.',
            ])
            ->assertRedirect();

        $this->assertSame('Shipped the redesign, QA signed off.', $task->refresh()->completion_note);
    }

    public function test_reopening_and_recompleting_a_task_preserves_the_reopened_marker()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        [$backlog, , $done] = $board->columns()->get()->all();

        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/tasks/{$task->id}/move", ['board_column_id' => $done->id, 'position' => 1]);
        $this->actingAs($user)->post("/tasks/{$task->id}/move", ['board_column_id' => $backlog->id, 'position' => 1]);
        $this->actingAs($user)->post("/tasks/{$task->id}/move", ['board_column_id' => $done->id, 'position' => 1]);

        $task->refresh();
        $this->assertNotNull($task->completed_at);
        $this->assertNotNull($task->reopened_at, 'reopened_at should stay set once a task has ever been reopened, even after re-completing.');
    }

    public function test_task_permalink_redirects_to_its_board_with_the_task_selected()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $board->columns()->first()->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get("/tasks/{$task->id}")
            ->assertRedirect("/boards/{$board->id}?task={$task->id}");
    }

    public function test_task_permalink_requires_view_access()
    {
        $outsider = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);

        $this->actingAs($outsider)->get("/tasks/{$task->id}")->assertForbidden();
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

    /**
     * Assigning someone outside the board's membership is itself how they
     * get access to it (TaskPolicy::view() + BoardPolicy::view()'s assigned-
     * task exception) — the same mechanism as adding a collaborator.
     */
    public function test_a_task_can_be_assigned_to_someone_outside_the_boards_membership()
    {
        $member = User::factory()->create()->assignRole('Employee');
        $outsider = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $board->members()->attach($member->id, ['access_level' => 'contribute']);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        $this->actingAs($member)
            ->from("/boards/{$board->id}")
            ->post("/boards/{$board->id}/tasks", [
                'title' => 'Cross-team work',
                'board_column_id' => $column->id,
                'priority' => 'medium',
                'primary_assignee_id' => $outsider->id,
            ])
            ->assertRedirect();

        $task = Task::query()->where('title', 'Cross-team work')->firstOrFail();
        $this->assertSame($outsider->id, $task->primary_assignee_id);
        $this->actingAs($outsider)->get("/boards/{$board->id}")->assertOk();
    }

    public function test_a_task_cannot_be_assigned_to_an_inactive_user()
    {
        $member = User::factory()->create()->assignRole('Employee');
        $inactive = User::factory()->create(['status' => User::STATUS_INACTIVE])->assignRole('Employee');
        $board = $this->boardWithColumns();
        $column = $board->columns()->first();

        $this->actingAs($member)
            ->from("/boards/{$board->id}")
            ->post("/boards/{$board->id}/tasks", [
                'title' => 'Needs an active assignee',
                'board_column_id' => $column->id,
                'priority' => 'medium',
                'primary_assignee_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('primary_assignee_id');

        $this->assertDatabaseMissing('tasks', ['title' => 'Needs an active assignee']);
    }
}
