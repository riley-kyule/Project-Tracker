<?php

namespace Tests\Feature\Dashboards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ceo_dashboard_requires_executive_role()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $manager = User::factory()->create()->assignRole('Department Manager');
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($employee)->get('/dashboards/ceo')->assertForbidden();
        $this->actingAs($manager)->get('/dashboards/ceo')->assertForbidden();
        $this->actingAs($ceo)->get('/dashboards/ceo')->assertOk();
    }

    public function test_ceo_dashboard_counts_and_department_rows()
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();

        $board = Board::factory()->create(['department_id' => $seo->id]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'department_id' => $seo->id,
            'due_at' => now()->subDay(),
            'ceo_priority' => true,
        ]);

        $response = $this->actingAs($ceo)->get('/dashboards/ceo')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(1, $props['counts']['overdue']);
        $this->assertSame(1, $props['counts']['ceo_priority']);

        $seoRow = collect($props['departmentPerformance'])->firstWhere('name', 'SEO');
        $this->assertSame(1, $seoRow['open']);
        $this->assertSame(1, $seoRow['overdue']);
    }

    public function test_department_dashboard_scopes_to_own_department()
    {
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $it = Department::query()->where('slug', 'it')->firstOrFail();

        $manager = User::factory()->create(['department_id' => $seo->id])->assignRole('Department Manager');

        $this->actingAs($manager)->get('/dashboards/department')->assertOk();

        // A manager cannot request another department.
        $response = $this->actingAs($manager)->get("/dashboards/department?department_id={$it->id}");
        $props = $response->viewData('page')['props'];
        $this->assertSame('SEO', $props['department']['name']);

        // Admins can inspect any department.
        $admin = User::factory()->create()->assignRole('Administrator');
        $response = $this->actingAs($admin)->get("/dashboards/department?department_id={$it->id}")->assertOk();
        $this->assertSame('IT', $response->viewData('page')['props']['department']['name']);
    }

    public function test_department_dashboard_rolls_up_child_departments()
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $content = Department::query()->where('slug', 'content')->firstOrFail();

        $this->assertSame($marketing->id, $seo->parent_department_id);
        $this->assertSame($marketing->id, $content->parent_department_id);

        $head = User::factory()->create(['department_id' => $marketing->id])->assignRole('Department Manager');
        $marketing->update(['manager_id' => $head->id]);

        $seoBoard = Board::factory()->create(['department_id' => $seo->id]);
        $seoColumn = BoardColumn::factory()->create(['board_id' => $seoBoard->id]);
        Task::factory()->create([
            'board_id' => $seoBoard->id,
            'board_column_id' => $seoColumn->id,
            'department_id' => $seo->id,
            'due_at' => now()->subDay(),
        ]);

        $contentBoard = Board::factory()->create(['department_id' => $content->id]);
        $contentColumn = BoardColumn::factory()->create(['board_id' => $contentBoard->id]);
        Task::factory()->create([
            'board_id' => $contentBoard->id,
            'board_column_id' => $contentColumn->id,
            'department_id' => $content->id,
        ]);

        $response = $this->actingAs($head)->get('/dashboards/department')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame('Marketing', $props['department']['name']);
        $this->assertSame(2, $props['counts']['open']);
        $this->assertSame(1, $props['counts']['overdue']);

        $subDepartments = collect($props['subDepartments']);
        $this->assertSame(1, $subDepartments->firstWhere('name', 'SEO')['open']);
        $this->assertSame(1, $subDepartments->firstWhere('name', 'Content')['open']);
    }

    public function test_department_dashboard_allows_assistant_manager()
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $assistant = User::factory()->create(['department_id' => $marketing->id]);
        $marketing->update(['assistant_manager_id' => $assistant->id]);

        $this->actingAs($assistant)->get('/dashboards/department')->assertOk();
    }

    public function test_task_report_requires_permission_and_filters()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($employee)->get('/reports/tasks')->assertForbidden();

        $board = Board::factory()->create();
        $blocked = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'blocked']);
        $backlog = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog']);

        Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $blocked->id, 'title' => 'Stuck task']);
        Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'title' => 'Normal task']);

        $response = $this->actingAs($admin)->get('/reports/tasks?filter=blocked')->assertOk();
        $titles = collect($response->viewData('page')['props']['tasks']['data'])->pluck('title');

        $this->assertTrue($titles->contains('Stuck task'));
        $this->assertFalse($titles->contains('Normal task'));
    }
}
