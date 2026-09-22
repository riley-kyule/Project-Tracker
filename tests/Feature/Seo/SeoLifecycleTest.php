<?php

namespace Tests\Feature\Seo;

use App\Jobs\GenerateSeoDailyCardReport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\User;
use App\Services\Seo\SeoCardLifecycleService;
use App\Services\Seo\SeoPerformanceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** SEO Board Requirements Specification v1.1 §4.2 — midnight close, snapshot, and next-card creation. */
class SeoLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function seoDepartment(): Department
    {
        return Department::query()->where('slug', 'seo')->firstOrFail();
    }

    public function test_a_past_due_card_is_closed_snapshotted_and_a_clean_next_card_is_opened(): void
    {
        Queue::fake();

        $department = $this->seoDepartment();
        $user = User::factory()->create()->assignRole('Employee');
        $employee = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        $yesterday = today()->subDay();
        // A Monday-Friday employee: force the test onto a working weekday regardless of when it runs.
        while ($yesterday->isWeekend()) {
            $yesterday = $yesterday->subDay();
        }

        $card = SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'work_date' => $yesterday, 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);
        $card->items()->create([
            'section' => 'monitoring', 'name' => 'Site availability check', 'weight' => 3,
            'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_SUBMITTED, 'submitted_at' => now(), 'position' => 1,
        ]);

        app(SeoCardLifecycleService::class)->closeDueCards();

        $card->refresh();
        $this->assertSame(SeoDailyCard::STATUS_CLOSED, $card->status);
        $this->assertNotNull($card->closed_at);
        $this->assertNotNull($card->closed_snapshot);
        $this->assertSame(3.0, (float) $card->employee_submitted_points); // the submitted item's full weight — provisional only.
        $this->assertCount(1, $card->closed_snapshot['items']);

        // The prior day's record is never overwritten or deleted (§11.1).
        $this->assertTrue($card->work_date->isSameDay($yesterday));

        // A clean card exists for the next applicable workday, with no status/evidence/comments carried forward.
        $next = SeoDailyCard::query()->where('employee_id', $employee->id)->where('id', '!=', $card->id)->first();
        $this->assertNotNull($next);
        $this->assertSame(SeoDailyCard::STATUS_OPEN, $next->status);
        $this->assertTrue($next->items()->where('employee_status', '!=', SeoCardItem::STATUS_NOT_STARTED)->doesntExist());

        Queue::assertPushed(GenerateSeoDailyCardReport::class, fn (GenerateSeoDailyCardReport $job) => $job->dailyCardId === $card->id);
    }

    /**
     * A regression test for a real gap: once a card leaves "today" it must
     * stay reachable — both visible (departmentAwaitingReview) and genuinely
     * actionable (the HOD can still decide it) — not just present in the
     * database with no way back to it through the app.
     */
    public function test_an_item_left_undecided_at_close_stays_visible_and_decidable_afterwards(): void
    {
        Queue::fake();

        $department = $this->seoDepartment();
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $hod = User::factory()->create()->assignRole('Department Manager');
        $marketing->update(['manager_id' => $hod->id]);

        $user = User::factory()->create()->assignRole('Employee');
        $employee = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        $yesterday = today()->subDay();
        while ($yesterday->isWeekend()) {
            $yesterday = $yesterday->subDay();
        }

        $card = SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'work_date' => $yesterday, 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);
        $item = $card->items()->create([
            'section' => 'monitoring', 'name' => 'Site availability check', 'weight' => 3,
            'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_SUBMITTED, 'submitted_at' => now(), 'position' => 1,
        ]);

        app(SeoCardLifecycleService::class)->closeDueCards();

        // Gone from "today" — that's expected, it's not today anymore.
        $today = app(SeoPerformanceQuery::class)->departmentToday($department);
        $this->assertEmpty(collect($today)->where('card_id', $card->id));

        // But still visible as backlog awaiting review...
        $awaiting = app(SeoPerformanceQuery::class)->departmentAwaitingReview($department);
        $this->assertNotEmpty(collect($awaiting)->where('card_id', $card->id));

        // ...and genuinely decidable, not just displayed.
        $this->actingAs($hod)->post("/seo-board/items/{$item->id}/decide", ['decision' => 'approved'])->assertRedirect();

        // The lone item on this card is worth all of its (small) denominator, so
        // approving it fully scores 100% of what was actually assigned — see
        // SeoScoringService::recalculateCard.
        $this->assertSame(100.0, (float) $card->fresh()->approved_points);
        $this->assertEmpty(collect(app(SeoPerformanceQuery::class)->departmentAwaitingReview($department))->where('card_id', $card->id));
    }

    public function test_closing_a_card_twice_is_a_no_op(): void
    {
        Queue::fake();

        $department = $this->seoDepartment();
        $user = User::factory()->create()->assignRole('Employee');
        $employee = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        $card = SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'work_date' => today()->subDay(), 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);

        $lifecycle = app(SeoCardLifecycleService::class);
        $lifecycle->closeDueCards();
        $countAfterFirst = SeoDailyCard::query()->where('employee_id', $employee->id)->count();

        $lifecycle->closeDueCards();
        $countAfterSecond = SeoDailyCard::query()->where('employee_id', $employee->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }
}
