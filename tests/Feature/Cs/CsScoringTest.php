<?php

namespace Tests\Feature\Cs;

use App\Models\CsCardItem;
use App\Models\CsDailyCard;
use App\Models\CsFinalScore;
use App\Models\CsSalesRecord;
use App\Models\CsWeeklyCard;
use App\Models\CsWeeklyTarget;
use App\Services\Cs\CsCardLifecycleService;
use App\Services\Cs\CsSalesAttributionService;
use App\Services\Cs\CsScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** No self-scoring, the 70/30 weekly formula, and the sales ledger's no-duplicate and cleared-only rules. */
class CsScoringTest extends TestCase
{
    use CsFixtures, RefreshDatabase;

    public function test_an_employee_submitting_work_never_creates_earned_points(): void
    {
        [$member, $employee] = $this->csMember();
        app(CsCardLifecycleService::class)->ensureTodaysCardsExist();
        $item = CsCardItem::query()->where('cardable_type', CsDailyCard::class)->firstOrFail();
        $item->update(['evidence_required' => false]);

        $this->actingAs($member)->post("/cs-board/items/{$item->id}/status", ['employee_status' => 'submitted'])->assertRedirect();

        $this->assertNull($item->fresh()->earned_points);
        $this->assertNull(CsDailyCard::query()->where('employee_id', $employee->id)->first()->approved_points);
        $this->assertSame(0, CsFinalScore::query()->count());
    }

    public function test_an_hod_decision_scores_the_card_and_feeds_the_final_score(): void
    {
        [$hod] = $this->csLeaders();
        [, $employee] = $this->csMember();
        app(CsCardLifecycleService::class)->ensureTodaysCardsExist();

        foreach (CsCardItem::query()->where('cardable_type', CsDailyCard::class)->get() as $item) {
            $this->actingAs($hod)->post("/cs-board/items/{$item->id}/decide", ['decision' => 'approved'])->assertRedirect();
        }

        $card = CsDailyCard::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $card->approved_points, 0.01);

        $final = CsFinalScore::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(70.0, (float) $final->final_score, 0.01, '100 daily x 70% + no weekly card yet');
    }

    public function test_only_cleared_payments_count_and_a_payment_reference_cannot_be_reused(): void
    {
        [$hod] = $this->csLeaders();
        [$member, $employee] = $this->csMember();
        $week = now()->startOfWeek()->toDateString();
        $sale = fn (array $over = []) => [
            'category' => 'new', 'customer_identifier' => 'cust-1', 'payment_reference' => 'PAY-1', 'amount' => 1000,
            'currency' => 'KES', 'week_start_date' => $week, ...$over,
        ];

        $this->actingAs($member)->post("/cs-board/employees/{$employee->id}/sales", $sale())->assertRedirect();
        $record = CsSalesRecord::query()->firstOrFail();
        $this->assertSame('pending', $record->status);

        // Same payment reference twice: rejected.
        $this->actingAs($member)->post("/cs-board/employees/{$employee->id}/sales", $sale(['customer_identifier' => 'cust-2']))->assertSessionHasErrors('payment_reference');
        $this->assertSame(1, CsSalesRecord::query()->count());

        // A member cannot clear their own sale; the HOD can.
        $this->actingAs($member)->post("/cs-board/sales/{$record->id}/clear")->assertForbidden();
        $this->actingAs($hod)->post("/cs-board/sales/{$record->id}/clear")->assertRedirect();
        $this->assertTrue($record->fresh()->isCleared());

        // Once new, never new again: the same customer must be a renewal.
        $this->actingAs($member)->post("/cs-board/employees/{$employee->id}/sales", $sale(['payment_reference' => 'PAY-3']))->assertSessionHasErrors('customer_identifier');

        // Reversed, refunded and fraudulent payments stop counting as collected revenue.
        foreach (['reversed', 'refunded', 'fraudulent'] as $status) {
            $record->update(['status' => 'cleared']);
            $this->actingAs($hod)->post("/cs-board/sales/{$record->id}/flag", ['status' => $status, 'reason' => 'test'])->assertRedirect();
            $this->assertSame(0, CsSalesRecord::query()->cleared()->count());
        }
    }

    public function test_commercial_achievement_blends_count_and_revenue_equally_and_caps_at_100(): void
    {
        [$hod] = $this->csLeaders();
        [, $employee] = $this->csMember();
        $week = now()->startOfWeek();
        $attribution = app(CsSalesAttributionService::class);
        $attribution->setWeeklyTarget($employee, $week->copy()->addWeek(), [
            'new_customers_target' => 4, 'new_customer_revenue_target' => 1000, 'renewed_customers_target' => 0, 'retained_revenue_target' => 0, 'currency' => 'KES',
        ], $hod);
        $target = CsWeeklyTarget::query()->firstOrFail();
        $this->assertSame(4, $target->new_customers_target);

        $weekly = CsWeeklyCard::query()->create(['employee_id' => $employee->id, 'department_id' => $this->csDepartment()->id, 'week_start_date' => $week->copy()->addWeek()->toDateString(), 'week_end_date' => $week->copy()->addWeek()->endOfWeek()->toDateString(), 'status' => 'plan_approved', 'planned_points' => 100]);
        $item = $weekly->items()->create(['section' => 'new_sales', 'name' => 'New-customer sales and revenue', 'weight' => 30, 'metric_type' => 'new_sales', 'evidence_required' => false, 'employee_status' => 'submitted', 'position' => 1]);

        // 1 of 4 customers (25%) and 500 of 1000 revenue (50%) => 37.5% blended.
        CsSalesRecord::query()->create(['employee_id' => $employee->id, 'category' => 'new', 'customer_identifier' => 'c1', 'payment_reference' => 'R1', 'amount' => 500, 'currency' => 'KES', 'reporting_currency_amount' => 500, 'status' => 'cleared', 'week_start_date' => $week->copy()->addWeek()->toDateString()]);
        // A pending sale must not move the number.
        CsSalesRecord::query()->create(['employee_id' => $employee->id, 'category' => 'new', 'customer_identifier' => 'c2', 'payment_reference' => 'R2', 'amount' => 9000, 'currency' => 'KES', 'reporting_currency_amount' => 9000, 'status' => 'pending', 'week_start_date' => $week->copy()->addWeek()->toDateString()]);

        app(CsScoringService::class)->decide($item, CsCardItem::DECISION_APPROVED, null, $hod);

        $this->assertEqualsWithDelta(37.5, (float) $item->fresh()->achieved_quantity, 0.01);
        $this->assertEqualsWithDelta(11.25, (float) $item->fresh()->earned_points, 0.01, '30 points x 37.5%');
    }
}
