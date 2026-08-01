<?php

namespace Tests\Feature\Console;

use App\Mail\CeoWeeklySummaryMail;
use App\Mail\WeeklyPersonalSummaryMail;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\ReportDelivery;
use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendWeeklySummariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CompanySetting::current()->update(['timezone' => 'Africa/Nairobi']);
    }

    public function test_not_due_before_the_departments_configured_time()
    {
        Mail::fake();

        // A Wednesday, well before Friday.
        $this->travelTo(Carbon::parse('2026-07-29 10:00:00', 'Africa/Nairobi'));

        $department = Department::factory()->create(['weekly_summary_time' => '16:00:00']);
        User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id])->assignRole('Employee');

        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('report_snapshots', 0);
    }

    public function test_sends_to_a_departments_employees_once_its_configured_time_passes()
    {
        Mail::fake();

        // Friday 2026-07-31, 16:00 Nairobi — exactly this department's cutoff.
        $this->travelTo(Carbon::parse('2026-07-31 16:00:00', 'Africa/Nairobi'));

        $department = Department::factory()->create(['weekly_summary_time' => '16:00:00']);
        $employee = User::factory()
            ->create(['status' => User::STATUS_ACTIVE, 'name' => 'Ada Lovelace', 'department_id' => $department->id])
            ->assignRole('Employee');

        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'primary_assignee_id' => $employee->id,
            'title' => 'Finish the report redesign',
            'completed_at' => Carbon::parse('2026-07-29 09:00:00', 'Africa/Nairobi'),
        ]);
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'primary_assignee_id' => $employee->id,
            'due_at' => Carbon::parse('2026-07-25 09:00:00', 'Africa/Nairobi'),
        ]);

        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        $sent = null;
        Mail::assertSent(WeeklyPersonalSummaryMail::class, function (WeeklyPersonalSummaryMail $mail) use (&$sent, $employee) {
            if ($mail->recipient->is($employee)) {
                $sent = $mail;
            }

            return $mail->recipient->is($employee);
        });

        $this->assertSame(1, $sent->completedCount);
        $this->assertTrue($sent->completed->contains(fn ($task) => $task['label'] === 'Finish the report redesign'));
        $this->assertSame(1, $sent->pendingBreakdown['overdue']);

        $snapshot = ReportSnapshot::query()
            ->where('report_type', ReportSnapshot::TYPE_WEEKLY_PERSONAL)
            ->where('user_id', $employee->id)
            ->firstOrFail();
        $this->assertSame('2026-07-31', $snapshot->report_date->toDateString());
        $this->assertDatabaseHas('report_deliveries', [
            'recipient_user_id' => $employee->id,
            'status' => ReportDelivery::STATUS_SENT,
        ]);
    }

    public function test_a_department_without_a_configured_time_is_never_sent()
    {
        Mail::fake();

        $this->travelTo(Carbon::parse('2026-07-31 23:00:00', 'Africa/Nairobi'));

        $department = Department::factory()->create(['weekly_summary_time' => null]);
        User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id])->assignRole('Employee');

        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_running_it_again_over_the_weekend_does_not_duplicate()
    {
        Mail::fake();

        $department = Department::factory()->create(['weekly_summary_time' => '16:00:00']);
        User::factory()->create(['status' => User::STATUS_ACTIVE, 'department_id' => $department->id])->assignRole('Employee');

        $this->travelTo(Carbon::parse('2026-07-31 16:00:00', 'Africa/Nairobi'));
        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        // Saturday — the job still catches up on a missed Friday, but must not resend this user.
        $this->travelTo(Carbon::parse('2026-08-01 09:00:00', 'Africa/Nairobi'));
        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        Mail::assertSent(WeeklyPersonalSummaryMail::class, 1);
        $this->assertDatabaseCount('report_snapshots', 1);
    }

    public function test_opted_out_employee_is_skipped()
    {
        Mail::fake();

        $this->travelTo(Carbon::parse('2026-07-31 16:00:00', 'Africa/Nairobi'));

        $department = Department::factory()->create(['weekly_summary_time' => '16:00:00']);
        User::factory()
            ->create([
                'status' => User::STATUS_ACTIVE,
                'department_id' => $department->id,
                'notification_preferences' => ['weekly_summary' => false],
            ])
            ->assignRole('Employee');

        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_ceo_weekly_summary_is_sent_once_its_configured_time_passes()
    {
        Mail::fake();

        $this->travelTo(Carbon::parse('2026-07-31 17:00:00', 'Africa/Nairobi'));

        $ceo = User::factory()->create()->assignRole('CEO');
        CompanySetting::current()->update(['ceo_weekly_summary_time' => '17:00:00']);

        $department = Department::factory()->create();
        $board = Board::factory()->create();
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'department_id' => $department->id,
            'title' => 'Ship the redesign',
            'completed_at' => Carbon::parse('2026-07-29 09:00:00', 'Africa/Nairobi'),
        ]);

        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        $sent = null;
        Mail::assertSent(CeoWeeklySummaryMail::class, function (CeoWeeklySummaryMail $mail) use (&$sent) {
            $sent = $mail;

            return true;
        });

        $this->assertSame(1, $sent->totalCompleted);
        $this->assertDatabaseHas('report_snapshots', [
            'report_type' => ReportSnapshot::TYPE_CEO_WEEKLY,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('report_deliveries', [
            'recipient_user_id' => $ceo->id,
            'status' => ReportDelivery::STATUS_SENT,
        ]);
    }

    public function test_ceo_weekly_summary_is_not_sent_without_a_configured_time()
    {
        Mail::fake();

        $this->travelTo(Carbon::parse('2026-07-31 23:00:00', 'Africa/Nairobi'));
        User::factory()->create()->assignRole('CEO');

        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        Mail::assertNotSent(CeoWeeklySummaryMail::class);
    }
}
