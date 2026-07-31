<?php

namespace Tests\Feature\Console;

use App\Mail\WeeklyPersonalSummaryMail;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\CompanySetting;
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

    public function test_not_due_before_friday_evening()
    {
        Mail::fake();

        // A Wednesday, well before the Friday 16:00 cutoff.
        $this->travelTo(Carbon::parse('2026-07-29 10:00:00', 'Africa/Nairobi'));

        User::factory()->create(['status' => User::STATUS_ACTIVE])->assignRole('Employee');

        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('report_snapshots', 0);
    }

    public function test_sends_to_active_employees_once_the_week_ends()
    {
        Mail::fake();

        // Friday 2026-07-31, 16:00 Nairobi — exactly the cutoff.
        $this->travelTo(Carbon::parse('2026-07-31 16:00:00', 'Africa/Nairobi'));

        $employee = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Ada Lovelace'])->assignRole('Employee');

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
        $this->assertTrue($sent->completed->contains('Finish the report redesign'));
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

    public function test_running_it_again_over_the_weekend_does_not_duplicate()
    {
        Mail::fake();

        $this->travelTo(Carbon::parse('2026-07-31 16:00:00', 'Africa/Nairobi'));
        User::factory()->create(['status' => User::STATUS_ACTIVE])->assignRole('Employee');

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
        User::factory()
            ->create(['status' => User::STATUS_ACTIVE, 'notification_preferences' => ['weekly_summary' => false]])
            ->assignRole('Employee');

        $this->artisan('ewms:send-weekly-summaries')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
