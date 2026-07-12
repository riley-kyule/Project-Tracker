<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDependencyTest extends TestCase
{
    use RefreshDatabase;

    private function boardWithColumns(?int $departmentId = null): Board
    {
        $board = Board::factory()->create(['department_id' => $departmentId, 'visibility' => Board::VISIBILITY_COMPANY]);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Backlog', 'slug' => 'backlog', 'position' => 1, 'semantic_status' => 'backlog']);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'In Progress', 'slug' => 'in-progress', 'position' => 2, 'semantic_status' => 'active']);

        return $board;
    }

    public function test_adding_a_dependency_and_preventing_a_cycle()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $backlog = $board->columns()->first();

        $taskA = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $user->id]);
        $taskB = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $user->id]);

        $this->actingAs($user)
            ->post("/tasks/{$taskB->id}/dependencies", ['predecessor_task_id' => $taskA->id])
            ->assertRedirect();

        $this->assertDatabaseHas('task_dependencies', ['predecessor_task_id' => $taskA->id, 'successor_task_id' => $taskB->id]);

        // A now depends on B (reverse) would create a cycle since B already depends on A.
        $this->actingAs($user)
            ->post("/tasks/{$taskA->id}/dependencies", ['predecessor_task_id' => $taskB->id])
            ->assertStatus(422);
    }

    public function test_moving_into_active_column_is_blocked_by_unresolved_dependency()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        [$backlog, $active] = $board->columns()->get()->all();

        $predecessor = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $user->id]);
        $successor = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $user->id]);

        TaskDependency::create(['predecessor_task_id' => $predecessor->id, 'successor_task_id' => $successor->id]);

        $this->actingAs($user)
            ->from('/boards/'.$board->id)
            ->post("/tasks/{$successor->id}/move", ['board_column_id' => $active->id, 'position' => 1])
            ->assertSessionHasErrors('dependencies');

        $this->assertSame($backlog->id, $successor->refresh()->board_column_id);
    }

    public function test_completed_predecessor_no_longer_blocks_the_move()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        [$backlog, $active] = $board->columns()->get()->all();

        $predecessor = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'created_by' => $user->id,
            'completed_at' => now(),
        ]);
        $successor = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $user->id]);

        TaskDependency::create(['predecessor_task_id' => $predecessor->id, 'successor_task_id' => $successor->id]);

        $this->actingAs($user)
            ->post("/tasks/{$successor->id}/move", ['board_column_id' => $active->id, 'position' => 1])
            ->assertRedirect();

        $this->assertSame($active->id, $successor->refresh()->board_column_id);
    }

    public function test_employee_cannot_override_but_department_manager_in_scope_can()
    {
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $it = Department::query()->where('slug', 'it')->firstOrFail();

        $employee = User::factory()->create(['department_id' => $seo->id])->assignRole('Employee');
        $wrongManager = User::factory()->create(['department_id' => $it->id])->assignRole('Department Manager');
        $rightManager = User::factory()->create(['department_id' => $seo->id])->assignRole('Department Manager');

        $board = $this->boardWithColumns($seo->id);
        [$backlog, $active] = $board->columns()->get()->all();

        $predecessor = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'department_id' => $seo->id, 'created_by' => $employee->id]);
        $successor = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'department_id' => $seo->id, 'created_by' => $employee->id]);

        TaskDependency::create(['predecessor_task_id' => $predecessor->id, 'successor_task_id' => $successor->id]);

        $this->actingAs($employee)
            ->post("/tasks/{$successor->id}/move", ['board_column_id' => $active->id, 'position' => 1, 'override_reason' => 'Client escalation'])
            ->assertSessionHasErrors('dependencies');

        // A manager outside the task's department cannot move it at all, let alone override.
        $this->actingAs($wrongManager)
            ->post("/tasks/{$successor->id}/move", ['board_column_id' => $active->id, 'position' => 1, 'override_reason' => 'Client escalation'])
            ->assertForbidden();

        $this->actingAs($rightManager)
            ->post("/tasks/{$successor->id}/move", ['board_column_id' => $active->id, 'position' => 1, 'override_reason' => 'Client escalation'])
            ->assertRedirect();

        $successor->refresh();
        $this->assertSame($active->id, $successor->board_column_id);

        $dependency = TaskDependency::query()->where('successor_task_id', $successor->id)->firstOrFail();
        $this->assertNotNull($dependency->overridden_at);
        $this->assertSame($rightManager->id, $dependency->overridden_by);
        $this->assertSame('Client escalation', $dependency->override_reason);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Task::class,
            'auditable_id' => $successor->id,
            'event' => 'dependency_overridden',
        ]);
    }

    public function test_dependency_removal_requires_task_update_permission()
    {
        $owner = User::factory()->create()->assignRole('Employee');
        $stranger = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $backlog = $board->columns()->first();

        $predecessor = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $owner->id]);
        $successor = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $owner->id]);
        $dependency = TaskDependency::create(['predecessor_task_id' => $predecessor->id, 'successor_task_id' => $successor->id]);

        $this->actingAs($stranger)->delete("/task-dependencies/{$dependency->id}")->assertForbidden();
        $this->actingAs($owner)->delete("/task-dependencies/{$dependency->id}")->assertRedirect();

        $this->assertDatabaseMissing('task_dependencies', ['id' => $dependency->id]);
    }
}
