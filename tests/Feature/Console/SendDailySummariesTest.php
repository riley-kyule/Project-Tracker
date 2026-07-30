<?php

namespace Tests\Feature\Console;

use App\Mail\DepartmentDailySummaryMail;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Comment;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendDailySummariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_summary_includes_todays_comments()
    {
        Mail::fake();

        $manager = User::factory()->create()->assignRole('Employee');
        $department = Department::factory()->create([
            'manager_id' => $manager->id,
            'daily_summary_time' => now()->format('H:i:s'),
        ]);

        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'department_id' => $department->id,
            'title' => 'Ship the landing page',
        ]);

        $commenter = User::factory()->create(['name' => 'Ada Lovelace'])->assignRole('Employee');
        Comment::factory()->create([
            'commentable_type' => Task::class,
            'commentable_id' => $task->id,
            'user_id' => $commenter->id,
            'body' => 'Copy is ready for review.',
            'created_at' => now(),
        ]);

        // A comment from yesterday must not leak into today's summary.
        Comment::factory()->create([
            'commentable_type' => Task::class,
            'commentable_id' => $task->id,
            'user_id' => $commenter->id,
            'body' => 'Old context, should not appear.',
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();

        Mail::assertQueued(DepartmentDailySummaryMail::class, function (DepartmentDailySummaryMail $mail) use ($department) {
            $lines = $mail->comments->get('Ship the landing page');

            return $mail->department->is($department)
                && $lines !== null
                && $lines->count() === 1
                && str_contains($lines->first(), 'Ada Lovelace: Copy is ready for review.');
        });
    }
}
