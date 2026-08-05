<?php

namespace Tests\Feature\Console;

use App\Jobs\ResetRecurringTask;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\CompanySetting;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ResetRecurringTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CompanySetting::current()->update(['timezone' => 'Africa/Nairobi']);
    }

    private function task(array $overrides = []): Task
    {
        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        return Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            ...$overrides,
        ]);
    }

    public function test_it_dispatches_a_job_for_a_due_daily_task()
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-08-05 07:00:00', 'Africa/Nairobi'));

        $task = $this->task(['auto_reset_frequency' => 'daily']);

        $this->artisan('ewms:reset-recurring-tasks')->assertSuccessful();

        Bus::assertDispatched(ResetRecurringTask::class, fn ($job) => $job->taskId === $task->id);
    }

    public function test_it_skips_a_task_before_the_reset_hour()
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-08-05 05:00:00', 'Africa/Nairobi'));

        $this->task(['auto_reset_frequency' => 'daily']);

        $this->artisan('ewms:reset-recurring-tasks')->assertSuccessful();

        Bus::assertNotDispatched(ResetRecurringTask::class);
    }

    public function test_it_skips_a_task_already_reset_this_period()
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-08-05 07:00:00', 'Africa/Nairobi'));

        $this->task(['auto_reset_frequency' => 'daily', 'last_auto_reset_at' => now()]);

        $this->artisan('ewms:reset-recurring-tasks')->assertSuccessful();

        Bus::assertNotDispatched(ResetRecurringTask::class);
    }

    public function test_it_skips_a_non_recurring_task()
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-08-05 07:00:00', 'Africa/Nairobi'));

        $this->task(['auto_reset_frequency' => null]);

        $this->artisan('ewms:reset-recurring-tasks')->assertSuccessful();

        Bus::assertNotDispatched(ResetRecurringTask::class);
    }

    public function test_it_skips_an_archived_task()
    {
        Bus::fake();
        $this->travelTo(Carbon::parse('2026-08-05 07:00:00', 'Africa/Nairobi'));

        $this->task(['auto_reset_frequency' => 'daily', 'archived_at' => now()->subDay()]);

        $this->artisan('ewms:reset-recurring-tasks')->assertSuccessful();

        Bus::assertNotDispatched(ResetRecurringTask::class);
    }

    public function test_a_weekly_task_is_not_dispatched_twice_in_the_same_week()
    {
        Bus::fake();
        $task = $this->task(['auto_reset_frequency' => 'weekly']);

        $this->travelTo(Carbon::parse('2026-08-03 07:00:00', 'Africa/Nairobi')); // Monday.
        $this->artisan('ewms:reset-recurring-tasks')->assertSuccessful();
        Bus::assertDispatched(ResetRecurringTask::class, 1);

        $task->update(['last_auto_reset_at' => now()]);

        $this->travelTo(Carbon::parse('2026-08-05 07:00:00', 'Africa/Nairobi')); // Wednesday, same week.
        $this->artisan('ewms:reset-recurring-tasks')->assertSuccessful();
        Bus::assertDispatched(ResetRecurringTask::class, 1);
    }
}
