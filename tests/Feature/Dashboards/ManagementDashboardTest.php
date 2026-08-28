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

    /**
     * The department dashboard is reachable by anyone department->leads() them
     * (manager or assistant manager by id), regardless of role/permissions —
     * unlike BoardPolicy::manage(), which requires the 'boards.manage'
     * permission before it lets a department lead see a restricted board.
     * whereIn('department_id', ...) alone doesn't know about that distinction,
     * so before Task::scopeVisibleTo() this assistant (no role, no
     * boards.manage) would have seen the restricted board's task too.
     */
    public function test_department_dashboard_hides_restricted_board_tasks_from_a_non_manager_lead()
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $assistant = User::factory()->create(['department_id' => $marketing->id]);
        $marketing->update(['assistant_manager_id' => $assistant->id]);

        $visibleBoard = Board::factory()->create(['department_id' => $marketing->id, 'visibility' => Board::VISIBILITY_DEPARTMENT]);
        $visibleColumn = BoardColumn::factory()->create(['board_id' => $visibleBoard->id]);
        Task::factory()->create([
            'board_id' => $visibleBoard->id,
            'board_column_id' => $visibleColumn->id,
            'department_id' => $marketing->id,
            'title' => 'Visible task',
        ]);

        $restrictedBoard = Board::factory()->create(['department_id' => $marketing->id, 'visibility' => Board::VISIBILITY_RESTRICTED]);
        $restrictedColumn = BoardColumn::factory()->create(['board_id' => $restrictedBoard->id]);
        Task::factory()->create([
            'board_id' => $restrictedBoard->id,
            'board_column_id' => $restrictedColumn->id,
            'department_id' => $marketing->id,
            'title' => 'Secret task',
        ]);

        $response = $this->actingAs($assistant)->get('/dashboards/department')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(1, $props['counts']['open']);

        $titles = collect($props['unassigned'])->pluck('title');
        $this->assertTrue($titles->contains('Visible task'));
        $this->assertFalse($titles->contains('Secret task'));
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

    /**
     * Paginates 50/page; the frontend Pagination component needs the full
     * paginator shape or page 2+ becomes unreachable with no error.
     */
    public function test_task_report_exposes_full_pagination_metadata()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog']);

        Task::factory()->count(55)->create(['board_id' => $board->id, 'board_column_id' => $column->id]);

        $props = $this->actingAs($admin)->get('/reports/tasks')->assertOk()->viewData('page')['props'];

        $this->assertSame(50, count($props['tasks']['data']));
        $this->assertSame(55, $props['tasks']['total']);
        $this->assertSame(2, $props['tasks']['last_page']);
        $this->assertNotEmpty($props['tasks']['links']);
    }

    public function test_task_report_can_be_sorted_by_an_allowed_column()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog']);

        Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id, 'title' => 'Charlie']);
        Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id, 'title' => 'Alice']);
        Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id, 'title' => 'Bob']);

        $props = $this->actingAs($admin)->get('/reports/tasks?sort=title&direction=asc')->assertOk()->viewData('page')['props'];

        $this->assertSame(['Alice', 'Bob', 'Charlie'], collect($props['tasks']['data'])->pluck('title')->all());
        $this->assertSame('title', $props['sort']);
    }
}
