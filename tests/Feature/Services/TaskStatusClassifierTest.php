<?php

namespace Tests\Feature\Services;

use App\Models\BoardColumn;
use App\Models\Task;
use App\Services\TaskStatusClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskStatusClassifierTest extends TestCase
{
    use RefreshDatabase;

    private TaskStatusClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new TaskStatusClassifier;
    }

    public function test_overdue_wins_even_over_a_blocked_column()
    {
        $column = BoardColumn::factory()->create(['semantic_status' => 'blocked']);
        $task = Task::factory()->create(['board_column_id' => $column->id, 'due_at' => now()->subDay()]);

        $this->assertSame(TaskStatusClassifier::OVERDUE, $this->classifier->classify($task->fresh()));
    }

    public function test_due_today_takes_priority_over_in_progress_column()
    {
        $this->travelTo(Carbon::parse('2026-07-31 09:00:00'));

        $column = BoardColumn::factory()->create(['semantic_status' => 'active']);
        $task = Task::factory()->create(['board_column_id' => $column->id, 'due_at' => Carbon::parse('2026-07-31 17:00:00')]);

        $this->assertSame(TaskStatusClassifier::DUE_TODAY, $this->classifier->classify($task->fresh()));
    }

    public function test_blocked_column_without_a_due_date()
    {
        $column = BoardColumn::factory()->create(['semantic_status' => 'blocked']);
        $task = Task::factory()->create(['board_column_id' => $column->id, 'due_at' => null]);

        $this->assertSame(TaskStatusClassifier::BLOCKED, $this->classifier->classify($task->fresh()));
    }

    public function test_review_column_is_awaiting_approval()
    {
        $column = BoardColumn::factory()->create(['semantic_status' => 'review']);
        $task = Task::factory()->create(['board_column_id' => $column->id, 'due_at' => null]);

        $this->assertSame(TaskStatusClassifier::AWAITING_APPROVAL, $this->classifier->classify($task->fresh()));
    }

    public function test_pending_reviewer_approval_is_awaiting_approval_regardless_of_column()
    {
        $column = BoardColumn::factory()->create(['semantic_status' => 'active']);
        $task = Task::factory()->create([
            'board_column_id' => $column->id,
            'due_at' => null,
            'approval_status' => Task::APPROVAL_PENDING,
        ]);

        $this->assertSame(TaskStatusClassifier::AWAITING_APPROVAL, $this->classifier->classify($task->fresh()));
    }

    public function test_future_due_date_is_planned_later()
    {
        $column = BoardColumn::factory()->create(['semantic_status' => 'backlog']);
        $task = Task::factory()->create(['board_column_id' => $column->id, 'due_at' => now()->addWeek()]);

        $this->assertSame(TaskStatusClassifier::PLANNED_LATER, $this->classifier->classify($task->fresh()));
    }

    public function test_active_column_without_a_due_date_is_in_progress()
    {
        $column = BoardColumn::factory()->create(['semantic_status' => 'active']);
        $task = Task::factory()->create(['board_column_id' => $column->id, 'due_at' => null]);

        $this->assertSame(TaskStatusClassifier::IN_PROGRESS, $this->classifier->classify($task->fresh()));
    }

    public function test_backlog_column_without_a_due_date_is_unscheduled_backlog()
    {
        $column = BoardColumn::factory()->create(['semantic_status' => 'backlog']);
        $task = Task::factory()->create(['board_column_id' => $column->id, 'due_at' => null]);

        $this->assertSame(TaskStatusClassifier::UNSCHEDULED_BACKLOG, $this->classifier->classify($task->fresh()));
    }

    public function test_counts_zero_fills_every_bucket()
    {
        $column = BoardColumn::factory()->create(['semantic_status' => 'backlog']);
        Task::factory()->create(['board_column_id' => $column->id, 'due_at' => null]);

        $counts = $this->classifier->counts(Task::query());

        $expected = array_fill_keys(TaskStatusClassifier::ALL, 0);
        $expected[TaskStatusClassifier::UNSCHEDULED_BACKLOG] = 1;

        $this->assertSame($expected, $counts);
    }
}
