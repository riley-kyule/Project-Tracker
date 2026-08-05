<?php

namespace Tests\Feature\Console;

use App\Mail\CeoDailySummaryMail;
use App\Mail\DepartmentDailySummaryMail;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Comment;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\ReportDelivery;
use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\User;
use App\Services\CommentService;
use App\Services\TaskMover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendDailySummariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CompanySetting::current()->update(['timezone' => 'Africa/Nairobi']);
    }

    public function test_department_summary_includes_todays_comments()
    {
        Mail::fake();

        $manager = User::factory()->create()->assignRole('Employee');
        $department = Department::factory()->create([
            'manager_id' => $manager->id,
            'daily_summary_time' => Carbon::now('Africa/Nairobi')->subMinute()->format('H:i:s'),
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

        Mail::assertSent(DepartmentDailySummaryMail::class, function (DepartmentDailySummaryMail $mail) use ($department, $task) {
            $entry = $mail->comments->firstWhere('title', 'Ship the landing page');

            return $mail->department->is($department)
                && $entry !== null
                && $entry['url'] === route('tasks.show', $task)
                && $entry['lines']->count() === 1
                && str_contains($entry['lines']->first(), 'Ada Lovelace: Copy is ready for review.');
        });

        $this->assertDatabaseCount('report_snapshots', 1);
        $snapshot = ReportSnapshot::first();
        $this->assertSame(ReportSnapshot::TYPE_DEPARTMENT_DAILY, $snapshot->report_type);
        $this->assertSame($department->id, $snapshot->department_id);

        $this->assertDatabaseHas('report_deliveries', [
            'report_snapshot_id' => $snapshot->id,
            'recipient_user_id' => $manager->id,
            'status' => ReportDelivery::STATUS_SENT,
        ]);
    }

    public function test_summary_is_not_dispatched_before_configured_time()
    {
        Mail::fake();

        $manager = User::factory()->create()->assignRole('Employee');
        Department::factory()->create([
            'manager_id' => $manager->id,
            'daily_summary_time' => Carbon::now('Africa/Nairobi')->addHour()->format('H:i:s'),
        ]);

        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('report_snapshots', 0);
    }

    public function test_department_summary_is_skipped_on_sunday_by_default()
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-08-02 09:00:00', 'Africa/Nairobi')); // A Sunday.

        $manager = User::factory()->create()->assignRole('Employee');
        Department::factory()->create([
            'manager_id' => $manager->id,
            'daily_summary_time' => '08:00:00',
            'send_sunday_reports' => false,
        ]);

        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('report_snapshots', 0);
    }

    public function test_department_summary_is_sent_on_sunday_when_opted_in()
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-08-02 09:00:00', 'Africa/Nairobi')); // A Sunday.

        $manager = User::factory()->create()->assignRole('Employee');
        Department::factory()->create([
            'manager_id' => $manager->id,
            'daily_summary_time' => '08:00:00',
            'send_sunday_reports' => true,
        ]);

        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();

        Mail::assertSent(DepartmentDailySummaryMail::class);
    }

    public function test_ceo_summary_is_skipped_on_sunday()
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-08-02 09:00:00', 'Africa/Nairobi')); // A Sunday.

        User::factory()->create()->assignRole('CEO');
        CompanySetting::current()->update(['ceo_summary_time' => '08:00:00']);

        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();

        Mail::assertNotSent(CeoDailySummaryMail::class);
    }

    public function test_running_the_command_twice_does_not_duplicate_the_report()
    {
        Mail::fake();

        $manager = User::factory()->create()->assignRole('Employee');
        Department::factory()->create([
            'manager_id' => $manager->id,
            'daily_summary_time' => Carbon::now('Africa/Nairobi')->subMinute()->format('H:i:s'),
        ]);

        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();
        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();

        Mail::assertSent(DepartmentDailySummaryMail::class, 1);
        $this->assertDatabaseCount('report_snapshots', 1);
        $this->assertDatabaseCount('report_deliveries', 1);
    }

    public function test_business_day_boundary_uses_company_timezone_not_utc()
    {
        Mail::fake();

        // 22:00 UTC is already 01:00 the *next* day in Africa/Nairobi (UTC+3,
        // no DST). A task completed just before this instant, on the UTC
        // calendar day, must still land in "today's" Nairobi business day.
        $this->travelTo(Carbon::parse('2026-07-31 22:00:00', 'UTC'));

        $manager = User::factory()->create()->assignRole('Employee');
        $department = Department::factory()->create([
            'manager_id' => $manager->id,
            'daily_summary_time' => '00:00:00',
        ]);

        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'department_id' => $department->id,
            'title' => 'Completed just after Nairobi midnight',
            'completed_at' => Carbon::parse('2026-07-31 21:30:00', 'UTC'),
        ]);

        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();

        Mail::assertSent(DepartmentDailySummaryMail::class, function (DepartmentDailySummaryMail $mail) {
            return $mail->completedToday === 1;
        });

        $snapshot = ReportSnapshot::first();
        $this->assertSame('2026-08-01', $snapshot->report_date->toDateString());
    }

    public function test_summary_includes_pending_breakdown_progress_notes_reopened_tasks_and_completeness()
    {
        Mail::fake();

        $manager = User::factory()->create()->assignRole('Employee');
        $department = Department::factory()->create([
            'manager_id' => $manager->id,
            'daily_summary_time' => Carbon::now('Africa/Nairobi')->subMinute()->format('H:i:s'),
        ]);

        $board = Board::factory()->create();
        $doneColumn = BoardColumn::factory()->create(['board_id' => $board->id, 'is_completion_column' => true]);
        $backlogColumn = BoardColumn::factory()->create(['board_id' => $board->id, 'semantic_status' => 'backlog']);

        $assignee = User::factory()
            ->create(['name' => 'Grace Hopper', 'department_id' => $department->id, 'status' => User::STATUS_ACTIVE])
            ->assignRole('Employee');

        // An overdue open task — counts toward pendingBreakdown['overdue'].
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlogColumn->id,
            'department_id' => $department->id,
            'due_at' => now()->subDay(),
        ]);

        // Completed with a note, reopened, then completed again: the mail
        // should prefer the note over the title and flag the reopen history.
        $migration = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlogColumn->id,
            'department_id' => $department->id,
            'primary_assignee_id' => $assignee->id,
            'title' => 'Migrate database',
        ]);
        TaskMover::move($migration, $doneColumn, 1, 'Ran the migration during the maintenance window.');
        TaskMover::move($migration->fresh(), $backlogColumn, 1);
        TaskMover::move($migration->fresh(), $doneColumn, 1);

        // Completed, then reopened, and left open — should surface in reopenedToday.
        $flaky = Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $backlogColumn->id,
            'department_id' => $department->id,
            'primary_assignee_id' => $assignee->id,
            'title' => 'Fix flaky test',
        ]);
        TaskMover::move($flaky, $doneColumn, 1);
        TaskMover::move($flaky->fresh(), $backlogColumn, 1);

        CommentService::createForTask(
            $migration->fresh(),
            $assignee,
            'Waiting on DBA sign-off for the next phase.',
            null,
            [],
            Comment::NOTE_BLOCKER,
        );

        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();

        $sent = null;
        Mail::assertSent(DepartmentDailySummaryMail::class, function (DepartmentDailySummaryMail $mail) use (&$sent) {
            $sent = $mail;

            return true;
        });

        $this->assertSame(1, $sent->pendingBreakdown['overdue']);

        $completedTask = $sent->breakdown->get('Grace Hopper')->first();
        $this->assertStringContainsString('Ran the migration during the maintenance window.', $completedTask['label']);
        $this->assertStringContainsString('previously reopened', $completedTask['label']);
        $this->assertSame(route('tasks.show', $migration), $completedTask['url']);

        $this->assertTrue($sent->reopenedToday->get('Grace Hopper')->contains(fn ($task) => $task['label'] === 'Fix flaky test'));

        $progressNoteEntry = $sent->progressNotes->firstWhere('title', 'Migrate database');
        $this->assertSame(route('tasks.show', $migration), $progressNoteEntry['url']);
        $progressNote = $progressNoteEntry['lines']->first();
        $this->assertStringContainsString('[blocker]', $progressNote);
        $this->assertStringContainsString('Waiting on DBA sign-off', $progressNote);

        $this->assertSame(1, $sent->completeness['active_members']);
        $this->assertSame(1, $sent->completeness['members_with_activity']);
        $this->assertSame(0, $sent->completeness['missing_activity']);
    }

    public function test_ceo_summary_is_sent_to_ceo_role()
    {
        Mail::fake();

        $ceo = User::factory()->create()->assignRole('CEO');
        CompanySetting::current()->update([
            'ceo_summary_time' => Carbon::now('Africa/Nairobi')->subMinute()->format('H:i:s'),
        ]);

        $this->artisan('ewms:send-daily-summaries')->assertSuccessful();

        Mail::assertSent(CeoDailySummaryMail::class);
        $this->assertDatabaseHas('report_snapshots', [
            'report_type' => ReportSnapshot::TYPE_CEO_DAILY,
            'department_id' => null,
        ]);
        $this->assertDatabaseHas('report_deliveries', [
            'recipient_user_id' => $ceo->id,
            'status' => ReportDelivery::STATUS_SENT,
        ]);
    }
}
