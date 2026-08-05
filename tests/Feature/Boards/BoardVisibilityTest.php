<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskAssigneeSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_boards_are_visible_to_all_employees()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);

        $this->actingAs($user)->get("/boards/{$board->id}")->assertOk();
    }

    public function test_department_boards_are_hidden_from_other_departments()
    {
        $department = Department::query()->where('slug', 'seo')->firstOrFail();
        $other = Department::query()->where('slug', 'it')->firstOrFail();

        $insider = User::factory()->create(['department_id' => $department->id])->assignRole('Employee');
        $outsider = User::factory()->create(['department_id' => $other->id])->assignRole('Employee');

        $board = Board::factory()->create([
            'visibility' => Board::VISIBILITY_DEPARTMENT,
            'department_id' => $department->id,
        ]);

        $this->actingAs($insider)->get("/boards/{$board->id}")->assertOk();
        $this->actingAs($outsider)->get("/boards/{$board->id}")->assertForbidden();
    }

    public function test_a_task_assignee_can_view_a_department_board_outside_their_own_department()
    {
        $department = Department::query()->where('slug', 'seo')->firstOrFail();
        $other = Department::query()->where('slug', 'it')->firstOrFail();

        $outsider = User::factory()->create(['department_id' => $other->id])->assignRole('Employee');

        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_DEPARTMENT, 'department_id' => $department->id]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'department_id' => $department->id,
            'primary_assignee_id' => $outsider->id,
        ]);
        // Real assignment goes through TaskAssigneeSync, which mirrors
        // primary_assignee_id into task_assignees — BoardPolicy checks that
        // pivot, not the column directly, so it needs to be populated here.
        TaskAssigneeSync::syncPrimary($task, null);

        $this->actingAs($outsider)->get("/boards/{$board->id}")->assertOk();
    }

    public function test_an_uninvolved_outsider_still_cannot_view_a_department_board()
    {
        $department = Department::query()->where('slug', 'seo')->firstOrFail();
        $other = Department::query()->where('slug', 'it')->firstOrFail();

        $outsider = User::factory()->create(['department_id' => $other->id])->assignRole('Employee');

        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_DEPARTMENT, 'department_id' => $department->id]);
        BoardColumn::factory()->create(['board_id' => $board->id]);

        $this->actingAs($outsider)->get("/boards/{$board->id}")->assertForbidden();
    }

    public function test_restricted_boards_require_membership()
    {
        $member = User::factory()->create()->assignRole('Employee');
        $stranger = User::factory()->create()->assignRole('Employee');
        $admin = User::factory()->create()->assignRole('Administrator');

        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $board->members()->attach($member->id, ['access_level' => 'contribute']);

        $this->actingAs($member)->get("/boards/{$board->id}")->assertOk();
        $this->actingAs($stranger)->get("/boards/{$board->id}")->assertForbidden();
        $this->actingAs($admin)->get("/boards/{$board->id}")->assertOk();
    }

    public function test_board_index_lists_only_visible_boards()
    {
        $user = User::factory()->create()->assignRole('Employee');
        Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY, 'name' => 'Everyone Board']);
        Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED, 'name' => 'Secret Board']);

        $response = $this->actingAs($user)->get('/boards');

        $response->assertOk();
        $this->assertStringContainsString('Everyone Board', $response->getContent());
        $this->assertStringNotContainsString('Secret Board', $response->getContent());
    }

    public function test_employees_cannot_create_boards()
    {
        $user = User::factory()->create()->assignRole('Employee');

        $this->actingAs($user)
            ->post('/boards', ['name' => 'Rogue Board', 'visibility' => 'company'])
            ->assertForbidden();
    }

    public function test_board_creation_seeds_default_columns()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->post('/boards', [
            'name' => 'New Board',
            'visibility' => 'company',
        ]);

        $board = Board::query()->where('name', 'New Board')->firstOrFail();
        $this->assertSame(8, $board->columns()->count());
        $this->assertSame(1, $board->columns()->where('is_completion_column', true)->count());
        $this->assertSame(1, $board->columns()->where('is_archive_column', true)->count());
    }

    public function test_board_creation_uses_the_departments_workflow_template()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $seo->update(['workflow_template' => Department::TEMPLATE_SEO]);

        $this->actingAs($admin)->post('/boards', [
            'name' => 'SEO Q3 Roadmap',
            'visibility' => 'department',
            'department_id' => $seo->id,
        ]);

        $board = Board::query()->where('name', 'SEO Q3 Roadmap')->firstOrFail();
        $this->assertSame(6, $board->columns()->count());
        $this->assertTrue($board->columns()->where('name', 'Research')->exists());
        $this->assertTrue($board->columns()->where('name', 'Monitoring')->exists());
        $this->assertFalse($board->columns()->where('name', 'Ideas')->exists());
    }

    public function test_board_creation_falls_back_to_default_columns_without_a_template()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $it = Department::query()->where('slug', 'it')->firstOrFail();
        $this->assertNull($it->workflow_template);

        $this->actingAs($admin)->post('/boards', [
            'name' => 'General IT Board',
            'visibility' => 'department',
            'department_id' => $it->id,
        ]);

        $board = Board::query()->where('name', 'General IT Board')->firstOrFail();
        $this->assertSame(8, $board->columns()->count());
        $this->assertTrue($board->columns()->where('name', 'Ideas')->exists());
    }
}
