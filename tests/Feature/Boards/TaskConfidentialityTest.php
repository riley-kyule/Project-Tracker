<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    private function confidentialTask(): Task
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        return Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'confidentiality' => Task::CONFIDENTIALITY_CONFIDENTIAL,
        ]);
    }

    public function test_normal_tasks_are_unaffected()
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $task = Task::factory()->create(['board_id' => $board->id]);
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get("/tasks/{$task->id}/detail")->assertOk();
    }

    public function test_ceo_and_administrator_always_view_confidential_tasks()
    {
        $task = $this->confidentialTask();
        $ceo = User::factory()->create()->assignRole('CEO');
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($ceo)->get("/tasks/{$task->id}/detail")->assertOk();
        $this->actingAs($admin)->get("/tasks/{$task->id}/detail")->assertOk();
    }

    public function test_department_manager_denied_without_explicit_grant()
    {
        $task = $this->confidentialTask();
        $manager = User::factory()->create()->assignRole('Department Manager');

        $this->actingAs($manager)->get("/tasks/{$task->id}/detail")->assertForbidden();
    }

    public function test_department_manager_allowed_once_granted()
    {
        $task = $this->confidentialTask();
        $manager = User::factory()->create()->assignRole('Department Manager');
        $task->confidentialGrants()->attach($manager->id);

        $this->actingAs($manager)->get("/tasks/{$task->id}/detail")->assertOk();
    }

    public function test_other_roles_are_always_denied_on_confidential_tasks()
    {
        $task = $this->confidentialTask();

        foreach (['IT Technician', 'Marketing', 'Customer Service', 'Employee', 'Viewer'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get("/tasks/{$task->id}/detail")->assertForbidden();
        }
    }

    public function test_only_ceo_or_administrator_can_change_confidentiality_or_manage_grants()
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $manager = User::factory()->create()->assignRole('Department Manager');
        // Task creator, so TaskPolicy::update passes overall — isolates the
        // assertion to the confidentiality field being silently dropped.
        $task = Task::factory()->create(['board_id' => $board->id, 'created_by' => $manager->id]);
        $grantee = User::factory()->create()->assignRole('Department Manager');

        $this->actingAs($manager)
            ->patch("/tasks/{$task->id}", ['confidentiality' => 'confidential'])
            ->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'confidentiality' => 'normal']);

        $this->actingAs($manager)
            ->post("/tasks/{$task->id}/confidential-grants", ['user_id' => $grantee->id])
            ->assertForbidden();

        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)
            ->patch("/tasks/{$task->id}", ['confidentiality' => 'confidential'])
            ->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'confidentiality' => 'confidential']);

        $this->actingAs($admin)
            ->post("/tasks/{$task->id}/confidential-grants", ['user_id' => $grantee->id])
            ->assertRedirect();
        $this->assertDatabaseHas('task_confidential_grants', ['task_id' => $task->id, 'user_id' => $grantee->id]);
    }

    public function test_confidential_tasks_do_not_appear_as_board_cards_to_unauthorized_viewers()
    {
        $task = $this->confidentialTask();
        $employee = User::factory()->create()->assignRole('Employee');

        $response = $this->actingAs($employee)->get("/boards/{$task->board_id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where(
            'board.columns',
            fn ($columns) => collect($columns)->flatMap(fn ($column) => $column['tasks'])->pluck('id')->doesntContain($task->id),
        ));
    }
}
