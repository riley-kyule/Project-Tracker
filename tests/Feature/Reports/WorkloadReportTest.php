<?php

namespace Tests\Feature\Reports;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkloadReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_cannot_view_the_workload_report()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/reports/workload')->assertForbidden();
    }

    public function test_department_manager_only_sees_their_own_department_and_cannot_filter()
    {
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $it = Department::query()->where('slug', 'it')->firstOrFail();

        $manager = User::factory()->create(['department_id' => $seo->id])->assignRole('Department Manager');
        $seoEmployee = User::factory()->create(['department_id' => $seo->id]);
        $itEmployee = User::factory()->create(['department_id' => $it->id]);

        $response = $this->actingAs($manager)->get('/reports/workload')->assertOk();
        $props = $response->viewData('page')['props'];

        $names = collect($props['people'])->pluck('name');
        $this->assertTrue($names->contains($seoEmployee->name));
        $this->assertFalse($names->contains($itEmployee->name));
        $this->assertFalse($props['canFilterDepartment']);
    }

    public function test_workload_counts_open_overdue_blocked_and_awaiting_review_tasks()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $employee = User::factory()->create(['department_id' => $seo->id]);

        $board = Board::factory()->create(['department_id' => $seo->id]);
        $backlog = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog']);
        $blocked = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'blocked']);
        $review = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'review']);

        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'department_id' => $seo->id,
            'primary_assignee_id' => $employee->id,
            'due_at' => now()->subDay(),
        ]);
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $blocked->id,
            'department_id' => $seo->id,
            'primary_assignee_id' => $employee->id,
        ]);
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $review->id,
            'department_id' => $seo->id,
            'primary_assignee_id' => $employee->id,
        ]);

        $response = $this->actingAs($admin)->get("/reports/workload?department_id={$seo->id}")->assertOk();
        $row = collect($response->viewData('page')['props']['people'])->firstWhere('id', $employee->id);

        $this->assertSame(3, $row['open_tasks']);
        $this->assertSame(1, $row['overdue_tasks']);
        $this->assertSame(1, $row['blocked_tasks']);
        $this->assertSame(1, $row['awaiting_review_tasks']);
    }
}
