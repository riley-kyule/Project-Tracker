<?php

namespace Tests\Feature\Cs;

use App\Jobs\GenerateCsDailyCardReport;
use App\Models\CsCardItem;
use App\Models\CsDailyCard;
use App\Models\CsTaskTemplate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\Cs\CsCardLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Customer Service Board Requirements Specification v1.0 §5.3: midnight close, next-day card, and proactive provisioning. */
class CsLifecycleTest extends TestCase
{
    use CsFixtures, RefreshDatabase;

    private function workingYesterday()
    {
        $yesterday = today()->subDay();
        while ($yesterday->isWeekend()) {
            $yesterday = $yesterday->subDay();
        }

        return $yesterday;
    }

    private function openCardFor(Employee $employee, $date): CsDailyCard
    {
        $card = CsDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $employee->user->department_id,
            'work_date' => $date, 'status' => CsDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);
        $card->items()->create([
            'section' => 'queue_clearance', 'name' => 'Morning queue clearance', 'weight' => 15,
            'evidence_required' => false, 'employee_status' => CsCardItem::STATUS_SUBMITTED, 'submitted_at' => now(), 'position' => 1,
        ]);

        return $card;
    }

    public function test_the_seeded_daily_and_weekly_libraries_each_total_exactly_100_points(): void
    {
        $this->assertEqualsWithDelta(100.0, (float) CsTaskTemplate::query()->where('card_type', 'daily')->active()->sum('default_weight'), 0.01);
        $this->assertEqualsWithDelta(100.0, (float) CsTaskTemplate::query()->where('card_type', 'weekly')->active()->sum('default_weight'), 0.01);
    }

    public function test_a_cs_members_past_card_is_closed_snapshotted_reported_and_a_clean_next_card_opens(): void
    {
        Queue::fake();
        [, $employee] = $this->csMember();
        $card = $this->openCardFor($employee, $this->workingYesterday());

        app(CsCardLifecycleService::class)->closeDueCards();

        $card->refresh();
        $this->assertSame(CsDailyCard::STATUS_CLOSED, $card->status);
        $this->assertNotNull($card->closed_snapshot);
        $this->assertCount(1, $card->closed_snapshot['items']);

        $next = CsDailyCard::query()->where('employee_id', $employee->id)->where('id', '!=', $card->id)->first();
        $this->assertNotNull($next);
        $this->assertSame(CsDailyCard::STATUS_OPEN, $next->status);
        $this->assertCount(7, $next->items);
        $this->assertTrue($next->items()->where('employee_status', '!=', CsCardItem::STATUS_NOT_STARTED)->doesntExist());

        Queue::assertPushed(GenerateCsDailyCardReport::class, fn (GenerateCsDailyCardReport $job) => $job->dailyCardId === $card->id);
    }

    /** The self-perpetuating stray card: without the check at close time it would recreate itself nightly and keep emailing the HOD. */
    public function test_a_stray_card_for_a_non_cs_employee_neither_perpetuates_nor_reports(): void
    {
        Queue::fake();
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $stray = User::factory()->create(['department_id' => $marketing->id])->assignRole('Customer Service');
        $employee = Employee::factory()->create(['user_id' => $stray->id, 'department_id' => $this->csDepartment()->id]);
        $card = $this->openCardFor($employee, $this->workingYesterday());

        $this->assertTrue($stray->can('cs.cards.view'));

        app(CsCardLifecycleService::class)->closeDueCards();

        $this->assertSame(CsDailyCard::STATUS_CLOSED, $card->fresh()->status, 'the stray card itself still closes cleanly');
        $this->assertSame(1, CsDailyCard::query()->where('employee_id', $employee->id)->count(), 'no next-day card is created');
        Queue::assertNotPushed(GenerateCsDailyCardReport::class);
    }

    public function test_a_leaders_stray_card_and_a_transferred_members_card_do_not_perpetuate(): void
    {
        Queue::fake();
        [$hod] = $this->csLeaders();
        $hodEmployee = Employee::query()->where('user_id', $hod->id)->firstOrFail();
        [$member, $memberEmployee] = $this->csMember();

        $hodCard = $this->openCardFor($hodEmployee, $this->workingYesterday());
        $memberCard = $this->openCardFor($memberEmployee, $this->workingYesterday());

        // Transferred out after the card was opened: membership is re-checked at close time.
        $member->update(['department_id' => Department::query()->where('slug', 'marketing')->value('id')]);

        app(CsCardLifecycleService::class)->closeDueCards();

        $this->assertSame(1, CsDailyCard::query()->where('employee_id', $hodEmployee->id)->count());
        $this->assertSame(1, CsDailyCard::query()->where('employee_id', $memberEmployee->id)->count());
        $this->assertSame(CsDailyCard::STATUS_CLOSED, $hodCard->fresh()->status);
        $this->assertSame(CsDailyCard::STATUS_CLOSED, $memberCard->fresh()->status);
        Queue::assertNotPushed(GenerateCsDailyCardReport::class);
    }

    public function test_provisioning_covers_active_cs_staff_only_and_is_idempotent(): void
    {
        [$hod] = $this->csLeaders();
        [, $active] = $this->csMember();
        [, $alsoActive] = $this->csMember();
        [, $terminated] = $this->csMember();
        $terminated->update(['employment_status' => Employee::STATUS_TERMINATED]);
        [, $suspendedUserEmployee] = $this->csMember(['status' => User::STATUS_INACTIVE]);
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $outsider = User::factory()->create(['department_id' => $marketing->id])->assignRole('Customer Service');
        $outsiderEmployee = Employee::factory()->create(['user_id' => $outsider->id, 'department_id' => $marketing->id]);
        $ceo = User::factory()->create(['department_id' => $this->csDepartment()->id])->assignRole('CEO');
        $ceoEmployee = Employee::factory()->create(['user_id' => $ceo->id, 'department_id' => $this->csDepartment()->id]);

        $lifecycle = app(CsCardLifecycleService::class);

        $this->assertSame(2, $lifecycle->ensureTodaysCardsExist());
        $this->assertSame(0, $lifecycle->ensureTodaysCardsExist(), 'a second run creates nothing');

        $withCards = CsDailyCard::query()->pluck('employee_id')->all();
        $this->assertEqualsCanonicalizing([$active->id, $alsoActive->id], $withCards);
        foreach ([$terminated, $suspendedUserEmployee, $outsiderEmployee, $ceoEmployee, Employee::query()->where('user_id', $hod->id)->first()] as $skipped) {
            $this->assertNotContains($skipped->id, $withCards);
        }
        foreach (CsDailyCard::all() as $card) {
            $this->assertCount(7, $card->items);
        }
    }

    public function test_the_scheduled_command_closes_and_provisions_in_one_run(): void
    {
        Queue::fake();
        [, $employee] = $this->csMember();
        $this->openCardFor($employee, $this->workingYesterday());
        [, $newcomer] = $this->csMember();

        $this->artisan('ewms:close-cs-daily-cards')->assertSuccessful();

        $this->assertTrue(CsDailyCard::query()->where('employee_id', $newcomer->id)->exists());
        $this->assertSame(CsDailyCard::STATUS_CLOSED, CsDailyCard::query()->where('employee_id', $employee->id)->orderBy('id')->first()->status);
    }
}
