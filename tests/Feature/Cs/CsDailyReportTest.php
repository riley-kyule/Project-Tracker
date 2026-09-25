<?php

namespace Tests\Feature\Cs;

use App\Jobs\GenerateCsDailyCardReport;
use App\Mail\CsDailyCardReportMail;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\CsCardItem;
use App\Models\CsDailyCard;
use App\Models\Department;
use App\Models\DepartmentNotificationRecipient;
use App\Models\Employee;
use App\Models\ReportDelivery;
use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\User;
use App\Services\Cs\CsCardLifecycleService;
use App\Services\Cs\CsScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** One combined daily email per CS member: the score card snapshot plus that person's Kanban activity. */
class CsDailyReportTest extends TestCase
{
    use CsFixtures, RefreshDatabase;

    public function test_the_daily_report_is_one_email_per_recipient_with_kanban_activity_and_score_data(): void
    {
        Mail::fake();

        [$hod, $assistant] = $this->csLeaders();
        $hod->update(['email' => 'hod@example.com']);
        $assistant->update(['email' => 'assistant@example.com']);
        $ceo = User::factory()->create(['email' => 'ceo@example.com'])->assignRole('CEO');
        // Extra configurable recipients: one repeats the HOD's address (any case) and must be de-duplicated.
        foreach (['HOD@example.com', 'ops@example.com'] as $email) {
            DepartmentNotificationRecipient::query()->create(['department_id' => $this->csDepartment()->id, 'email' => $email, 'is_active' => true, 'effective_from' => now()->subDay()]);
        }

        [$member, $employee] = $this->csMember();
        $board = $this->csBoard();
        $todo = $board->columns()->create(['name' => 'New', 'slug' => 'new', 'position' => 1, 'semantic_status' => 'idea']);
        $blockedColumn = $board->columns()->create(['name' => 'Waiting', 'slug' => 'waiting', 'position' => 2, 'semantic_status' => 'blocked']);

        $task = fn (array $attrs) => Task::factory()->create([
            'board_id' => $board->id, 'board_column_id' => $todo->id, 'department_id' => $board->department_id,
            'created_by' => $hod->id, 'primary_assignee_id' => $member->id, ...$attrs,
        ]);
        $created = $task(['title' => 'Chase renewal for Acme', 'created_by' => $member->id]);
        $completed = $task(['title' => 'Resolve billing complaint', 'completed_at' => now()]);
        $blocked = $task(['title' => 'Waiting on payment proof', 'board_column_id' => $blockedColumn->id]);
        $overdue = $task(['title' => 'Send renewal reminder', 'due_at' => now()->subDay()]);
        $someoneElses = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $todo->id, 'department_id' => $board->department_id, 'created_by' => $hod->id, 'title' => 'Not this member']);
        AuditLog::query()->create(['actor_id' => $member->id, 'auditable_type' => (new Task)->getMorphClass(), 'auditable_id' => $completed->id, 'event' => 'moved', 'created_at' => now()]);

        $lifecycle = app(CsCardLifecycleService::class);
        $card = $lifecycle->createNextCard($employee, $member->department_id, $lifecycle->businessDay()->subDay());
        $card = CsDailyCard::query()->whereKey($card->id)->firstOrFail();
        $card->update(['work_date' => $lifecycle->businessDay()->toDateString()]);
        $item = $card->items()->first();
        app(CsScoringService::class)->decide($item, CsCardItem::DECISION_APPROVED, null, $hod);

        GenerateCsDailyCardReport::dispatchSync($card->id);

        $recipients = ['ceo@example.com', 'hod@example.com', 'assistant@example.com', 'ops@example.com'];
        Mail::assertSent(CsDailyCardReportMail::class, count($recipients));
        foreach ($recipients as $email) {
            Mail::assertSent(CsDailyCardReportMail::class, fn (CsDailyCardReportMail $mail) => $mail->hasTo($email));
        }
        Mail::assertNotSent(CsDailyCardReportMail::class, fn (CsDailyCardReportMail $mail) => $mail->hasTo('member@example.com'));

        Mail::assertSent(CsDailyCardReportMail::class, function (CsDailyCardReportMail $mail) use ($created, $completed, $blocked, $overdue, $someoneElses) {
            $kanban = $mail->payload['kanban'];
            $titles = fn (string $key) => collect($kanban[$key])->pluck('title')->all();

            return $mail->payload['approved_points'] !== null
                && (float) $mail->payload['approved_points'] > 0
                && $mail->payload['planned_points'] === 100
                && $kanban['counts']['created'] === 1 && $titles('created') === [$created->title]
                && $kanban['counts']['completed'] === 1 && $titles('completed') === [$completed->title]
                && $kanban['counts']['moved'] === 1 && $titles('moved') === [$completed->title]
                && $titles('blocked') === [$blocked->title]
                && $titles('overdue') === [$overdue->title]
                && ! in_array($someoneElses->title, collect($kanban)->except('counts')->flatten(1)->pluck('title')->all(), true);
        });

        $snapshot = ReportSnapshot::query()->where('report_type', ReportSnapshot::TYPE_CS_DAILY_CARD)->firstOrFail();
        $this->assertArrayHasKey('kanban', $snapshot->payload);
        $this->assertSame(4, ReportDelivery::query()->where('report_snapshot_id', $snapshot->id)->count());
    }

    public function test_a_non_cs_employees_card_produces_no_report(): void
    {
        Mail::fake();
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $stray = User::factory()->create(['department_id' => $marketing->id])->assignRole('Customer Service');
        $employee = Employee::factory()->create(['user_id' => $stray->id, 'department_id' => $marketing->id]);
        $this->csLeaders();
        User::factory()->create()->assignRole('CEO');

        $yesterday = today()->subDay();
        $card = CsDailyCard::query()->create(['employee_id' => $employee->id, 'department_id' => $marketing->id, 'work_date' => $yesterday, 'status' => 'open', 'planned_points' => 100]);
        $card->items()->create(['section' => 's', 'name' => 'n', 'weight' => 100, 'evidence_required' => false, 'employee_status' => 'not_started', 'position' => 1]);

        app(CsCardLifecycleService::class)->closeDueCards();

        Mail::assertNothingSent();
        $this->assertSame(0, ReportSnapshot::query()->where('report_type', ReportSnapshot::TYPE_CS_DAILY_CARD)->count());
        $this->assertNotNull(CompanySetting::current());
    }
}
