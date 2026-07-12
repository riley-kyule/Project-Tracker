<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\Label;
use App\Models\RecurrenceRule;
use App\Models\Task;
use App\Models\User;
use App\Notifications\RecurrenceMissed;
use App\Services\RecurrenceService;
use App\Services\TaskMover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecurringTaskTest extends TestCase
{
    use RefreshDatabase;

    private function boardWithColumns(?int $departmentId = null): Board
    {
        $board = Board::factory()->create(['department_id' => $departmentId, 'visibility' => Board::VISIBILITY_COMPANY]);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Backlog', 'slug' => 'backlog', 'position' => 1, 'semantic_status' => 'backlog']);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Done', 'slug' => 'done', 'position' => 2, 'semantic_status' => 'completed', 'is_completion_column' => true]);

        return $board;
    }

    public function test_employee_cannot_create_a_recurrence_rule()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $board->columns()->first()->id, 'created_by' => $employee->id]);

        $this->actingAs($employee)
            ->post("/tasks/{$task->id}/recurrence", ['frequency' => 'weekly', 'interval_value' => 1])
            ->assertForbidden();
    }

    public function test_department_manager_can_make_a_task_recurring()
    {
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $manager = User::factory()->create(['department_id' => $seo->id])->assignRole('Department Manager');
        $board = $this->boardWithColumns($seo->id);
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $board->columns()->first()->id,
            'department_id' => $seo->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->post("/tasks/{$task->id}/recurrence", ['frequency' => 'weekly', 'interval_value' => 2])
            ->assertRedirect();

        $task->refresh();
        $this->assertNotNull($task->recurrence_rule_id);

        $rule = RecurrenceRule::query()->findOrFail($task->recurrence_rule_id);
        $this->assertSame('weekly', $rule->frequency);
        $this->assertSame(2, $rule->interval_value);
        $this->assertNotNull($rule->next_run_at);

        // A task can only have one active rule.
        $this->actingAs($manager)
            ->post("/tasks/{$task->id}/recurrence", ['frequency' => 'daily'])
            ->assertStatus(422);
    }

    public function test_stopping_recurrence_deactivates_the_rule()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = $this->boardWithColumns();
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $board->columns()->first()->id, 'created_by' => $admin->id]);
        $rule = RecurrenceRule::factory()->create(['template_task_id' => $task->id, 'created_by' => $admin->id]);
        $task->update(['recurrence_rule_id' => $rule->id]);

        $this->actingAs($admin)
            ->patch("/recurrence-rules/{$rule->id}", ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse($rule->refresh()->is_active);
    }

    public function test_due_rule_generates_an_instance_and_advances_next_run()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $assignee = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $backlog = $board->columns()->first();

        $template = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlog->id,
            'created_by' => $admin->id,
            'primary_assignee_id' => $assignee->id,
            'title' => 'Weekly report',
            'priority' => 'high',
        ]);
        $label = Label::factory()->create();
        $template->labels()->attach($label->id);

        $rule = RecurrenceRule::factory()->create([
            'template_task_id' => $template->id,
            'frequency' => 'weekly',
            'interval_value' => 1,
            'next_run_at' => now()->subHour(),
            'created_by' => $admin->id,
        ]);

        $generated = RecurrenceService::generateDueInstances();

        $this->assertSame(1, $generated);

        $instance = Task::query()->where('recurrence_rule_id', $rule->id)->firstOrFail();
        $this->assertSame('Weekly report', $instance->title);
        $this->assertSame('high', $instance->priority);
        $this->assertSame($assignee->id, $instance->primary_assignee_id);
        $this->assertTrue($instance->labels->contains($label));
        $this->assertSame($backlog->id, $instance->board_column_id);

        $rule->refresh();
        $this->assertTrue($rule->next_run_at->isFuture());
    }

    public function test_missed_schedule_notifies_the_rule_creator()
    {
        Notification::fake();

        $admin = User::factory()->create()->assignRole('Administrator');
        $board = $this->boardWithColumns();
        $template = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $board->columns()->first()->id, 'created_by' => $admin->id]);

        RecurrenceRule::factory()->create([
            'template_task_id' => $template->id,
            'frequency' => 'daily',
            'next_run_at' => now()->subDays(3),
            'created_by' => $admin->id,
        ]);

        RecurrenceService::generateDueInstances();

        Notification::assertSentTo($admin, RecurrenceMissed::class);
    }

    public function test_after_completion_rule_generates_on_task_completion_only()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = $this->boardWithColumns();
        [$backlog, $done] = $board->columns()->get()->all();

        $template = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $backlog->id, 'created_by' => $admin->id]);
        $rule = RecurrenceRule::factory()->create([
            'template_task_id' => $template->id,
            'frequency' => 'after_completion',
            'next_run_at' => null,
            'created_by' => $admin->id,
        ]);
        $template->update(['recurrence_rule_id' => $rule->id]);

        $generatedInstances = fn () => Task::query()->where('recurrence_rule_id', $rule->id)->where('id', '!=', $template->id);

        // Scheduled sweep must not touch event-driven rules.
        $this->assertSame(0, RecurrenceService::generateDueInstances());
        $this->assertSame(0, $generatedInstances()->count());

        TaskMover::move($template, $done, 1);

        $instances = $generatedInstances()->get();
        $this->assertCount(1, $instances);
        $this->assertSame($template->id, $instances->first()->previous_recurrence_task_id);
        $this->assertSame($backlog->id, $instances->first()->board_column_id);

        // Moving the same completed task again (e.g. reordering within Done) must not duplicate.
        TaskMover::move($template->fresh(), $done, 1);
        $this->assertSame(1, $generatedInstances()->count());
    }
}
