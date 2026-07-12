<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(): Task
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        return Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);
    }

    public function test_starting_and_stopping_a_timer_updates_actual_minutes()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($user)->post("/tasks/{$task->id}/time-entries/start")->assertRedirect();

        $entry = TimeEntry::query()->firstOrFail();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertTrue($entry->isRunning());

        $entry->update(['started_at' => now()->subMinutes(45)]);

        $this->actingAs($user)->post("/time-entries/{$entry->id}/stop")->assertRedirect();

        $entry->refresh();
        $this->assertNotNull($entry->ended_at);
        $this->assertEqualsWithDelta(45 * 60, $entry->duration_seconds, 5);
        $this->assertSame(45, $task->refresh()->actual_minutes);
    }

    public function test_a_user_cannot_run_two_timers_at_once()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $taskA = $this->makeTask();
        $taskB = $this->makeTask();

        $this->actingAs($user)->post("/tasks/{$taskA->id}/time-entries/start")->assertRedirect();

        $this->actingAs($user)
            ->from('/boards/1')
            ->post("/tasks/{$taskB->id}/time-entries/start")
            ->assertSessionHasErrors('timer');

        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_only_the_owner_can_stop_their_timer()
    {
        $owner = User::factory()->create()->assignRole('Employee');
        $stranger = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($owner)->post("/tasks/{$task->id}/time-entries/start");
        $entry = TimeEntry::query()->firstOrFail();

        $this->actingAs($stranger)->post("/time-entries/{$entry->id}/stop")->assertForbidden();
    }

    public function test_manual_entry_requires_approval_before_counting_toward_actual_minutes()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $admin = User::factory()->create()->assignRole('Administrator');
        $task = $this->makeTask();

        $this->actingAs($employee)->post("/tasks/{$task->id}/time-entries", [
            'duration_minutes' => 90,
            'work_location' => 'remote',
            'adjustment_reason' => 'Forgot to start the timer',
        ])->assertRedirect();

        $entry = TimeEntry::query()->firstOrFail();
        $this->assertSame('pending', $entry->adjustment_status);
        $this->assertSame(0, $task->refresh()->actual_minutes);

        $this->actingAs($admin)->post("/time-entries/{$entry->id}/approve")->assertRedirect();

        $entry->refresh();
        $this->assertSame('approved', $entry->adjustment_status);
        $this->assertSame($admin->id, $entry->approved_by);
        $this->assertSame(90, $task->refresh()->actual_minutes);
    }

    public function test_rejected_manual_entry_never_counts()
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $admin = User::factory()->create()->assignRole('Administrator');
        $task = $this->makeTask();

        $this->actingAs($employee)->post("/tasks/{$task->id}/time-entries", [
            'duration_minutes' => 60,
            'adjustment_reason' => 'Client call',
        ]);
        $entry = TimeEntry::query()->firstOrFail();

        $this->actingAs($admin)->post("/time-entries/{$entry->id}/reject")->assertRedirect();

        $this->assertSame('rejected', $entry->refresh()->adjustment_status);
        $this->assertSame(0, $task->refresh()->actual_minutes);

        // A resolved entry cannot be approved after the fact.
        $this->actingAs($admin)->post("/time-entries/{$entry->id}/approve")->assertStatus(422);
    }

    public function test_only_department_scoped_manager_can_approve()
    {
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $it = Department::query()->where('slug', 'it')->firstOrFail();

        $employee = User::factory()->create(['department_id' => $seo->id])->assignRole('Employee');
        $wrongManager = User::factory()->create(['department_id' => $it->id])->assignRole('Department Manager');
        $rightManager = User::factory()->create(['department_id' => $seo->id])->assignRole('Department Manager');

        $board = Board::factory()->create(['department_id' => $seo->id, 'visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id, 'department_id' => $seo->id]);

        $this->actingAs($employee)->post("/tasks/{$task->id}/time-entries", [
            'duration_minutes' => 20,
            'adjustment_reason' => 'Ad hoc support',
        ]);
        $entry = TimeEntry::query()->firstOrFail();

        $this->actingAs($wrongManager)->post("/time-entries/{$entry->id}/approve")->assertForbidden();
        $this->actingAs($rightManager)->post("/time-entries/{$entry->id}/approve")->assertRedirect();
    }
}
