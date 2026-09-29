<?php

namespace Tests\Feature\Cs;

use App\Models\CsActivityRecord;
use App\Models\CsContinuityCheck;
use App\Models\CsContinuityIssue;
use App\Models\CsPlatformAssignment;
use App\Models\CsSalesRecord;
use App\Services\Cs\CsCommercialTotals;
use App\Services\Cs\CsPerformanceQuery;
use App\Services\Cs\CsSalesAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Customer Service Board Requirements Specification v1.0 — the five gaps
 * closed after the initial Kanban merge: shared-sale splitting, additional
 * sales above quota (§3), daily sales activity logging (§5.1), assigned
 * platform continuity checks and the issue tracker (§5.2), and historical
 * platform-assignment reconstruction (§12).
 */
class CsGapClosingTest extends TestCase
{
    use CsFixtures, RefreshDatabase;

    public function test_a_shared_sale_splits_revenue_but_keeps_the_customer_count_with_the_primary(): void
    {
        [, $primary] = $this->csMember();
        [, $secondary] = $this->csMember();
        $week = now()->startOfWeek();

        CsSalesRecord::query()->create([
            'employee_id' => $primary->id,
            'category' => 'new',
            'customer_identifier' => 'shared-cust',
            'payment_reference' => 'SHARED-1',
            'amount' => 1000,
            'currency' => 'KES',
            'reporting_currency_amount' => 1000,
            'status' => 'cleared',
            'attribution_type' => CsSalesRecord::ATTRIBUTION_SHARED,
            'shared_with_employee_id' => $secondary->id,
            'split_percentage' => 30,
            'week_start_date' => $week->toDateString(),
        ]);

        $totals = app(CsCommercialTotals::class);
        $primaryTotals = $totals->forEmployee($primary->id, $week, ['new'], 0, 0);
        $secondaryTotals = $totals->forEmployee($secondary->id, $week, ['new'], 0, 0);

        // Primary keeps the remainder (70%) and the whole customer count; the
        // secondary only ever receives their revenue share, never a count.
        $this->assertEqualsWithDelta(700.0, $primaryTotals['revenue'], 0.01);
        $this->assertSame(1, $primaryTotals['customers']);
        $this->assertEqualsWithDelta(300.0, $secondaryTotals['revenue'], 0.01);
        $this->assertSame(0, $secondaryTotals['customers']);

        // Together the two shares sum to exactly the cleared amount.
        $this->assertEqualsWithDelta(1000.0, $primaryTotals['revenue'] + $secondaryTotals['revenue'], 0.01);
    }

    public function test_the_hod_can_clear_a_sale_as_shared_through_the_endpoint(): void
    {
        [$hod] = $this->csLeaders();
        [$member, $employee] = $this->csMember();
        [, $secondary] = $this->csMember();
        $week = now()->startOfWeek()->toDateString();

        $this->actingAs($member)->post("/cs-board/employees/{$employee->id}/sales", [
            'category' => 'new', 'customer_identifier' => 'cust-x', 'payment_reference' => 'PAY-X',
            'amount' => 500, 'currency' => 'KES', 'week_start_date' => $week,
        ])->assertRedirect();
        $record = CsSalesRecord::query()->firstOrFail();

        $this->actingAs($hod)->post("/cs-board/sales/{$record->id}/clear", [
            'attribution_type' => 'shared', 'shared_with_employee_id' => $secondary->id, 'split_percentage' => 40,
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame('shared', $record->attribution_type);
        $this->assertSame($secondary->id, $record->shared_with_employee_id);
        $this->assertEqualsWithDelta(40.0, (float) $record->split_percentage, 0.01);
    }

    public function test_additional_sales_above_quota_are_reported_separately_from_capped_achievement(): void
    {
        [$hod] = $this->csLeaders();
        [, $employee] = $this->csMember();
        $week = now()->startOfWeek();

        app(CsSalesAttributionService::class)->setWeeklyTarget($employee, $week, [
            'new_customers_target' => 2, 'new_customer_revenue_target' => 1000,
            'renewed_customers_target' => 0, 'retained_revenue_target' => 0, 'currency' => 'KES',
        ], $hod);

        // 3 customers against a target of 2, 1500 revenue against a target of 1000.
        foreach ([['c1', 'R1', 600], ['c2', 'R2', 600], ['c3', 'R3', 300]] as [$cust, $ref, $amount]) {
            CsSalesRecord::query()->create([
                'employee_id' => $employee->id, 'category' => 'new', 'customer_identifier' => $cust,
                'payment_reference' => $ref, 'amount' => $amount, 'currency' => 'KES',
                'reporting_currency_amount' => $amount, 'status' => 'cleared', 'week_start_date' => $week->toDateString(),
            ]);
        }

        $summary = app(CsPerformanceQuery::class)->commercialSummary($employee, $week);

        $this->assertSame(3, $summary['new_customers']);
        $this->assertSame(1, $summary['new_customers_additional'], '3 achieved against a target of 2');
        $this->assertEqualsWithDelta(1500.0, $summary['new_customer_revenue'], 0.01);
        $this->assertEqualsWithDelta(500.0, $summary['new_customer_revenue_additional'], 0.01, '1500 achieved against a target of 1000');
    }

    public function test_an_employee_logs_activity_across_every_workstream(): void
    {
        [, $employee] = $this->csMember();
        $member = $employee->user;

        foreach (CsActivityRecord::WORKSTREAMS as $workstream) {
            $this->actingAs($member)->post("/cs-board/employees/{$employee->id}/activities", [
                'workstream' => $workstream,
                'customer_identifier' => "cust-{$workstream}",
                'stage' => CsActivityRecord::STAGE_ATTEMPTED_CONTACT,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(count(CsActivityRecord::WORKSTREAMS), CsActivityRecord::query()->where('employee_id', $employee->id)->count());
    }

    public function test_an_activity_record_can_be_amended_as_the_conversation_progresses_but_only_by_its_owner(): void
    {
        [, $employee] = $this->csMember();
        [, $other] = $this->csMember();
        $member = $employee->user;

        $this->actingAs($member)->post("/cs-board/employees/{$employee->id}/activities", [
            'workstream' => CsActivityRecord::WORKSTREAM_NEW_CUSTOMER,
            'customer_identifier' => 'cust-1',
            'stage' => CsActivityRecord::STAGE_ATTEMPTED_CONTACT,
        ]);
        $record = CsActivityRecord::query()->firstOrFail();

        $this->actingAs($other->user)->patch("/cs-board/activities/{$record->id}", ['stage' => CsActivityRecord::STAGE_REGISTRATION])
            ->assertForbidden();

        $this->actingAs($member)->patch("/cs-board/activities/{$record->id}", ['stage' => CsActivityRecord::STAGE_REGISTRATION])
            ->assertRedirect();

        $this->assertSame(CsActivityRecord::STAGE_REGISTRATION, $record->fresh()->stage);
    }

    public function test_a_continuity_check_reporting_an_issue_must_include_notes(): void
    {
        [, $employee] = $this->csMember();
        $member = $employee->user;

        $this->actingAs($member)->post("/cs-board/employees/{$employee->id}/continuity-checks", [
            'check_type' => CsContinuityCheck::TYPE_REGISTRATION_LOGIN,
            'status' => 'issue_found',
        ])->assertSessionHasErrors('notes');
        $this->assertSame(0, CsContinuityCheck::query()->count());

        $this->actingAs($member)->post("/cs-board/employees/{$employee->id}/continuity-checks", [
            'check_type' => CsContinuityCheck::TYPE_REGISTRATION_LOGIN,
            'status' => 'issue_found',
            'notes' => 'Login page returns a 500 for this platform.',
        ])->assertRedirect();

        $check = CsContinuityCheck::query()->firstOrFail();
        $this->assertSame('issue_found', $check->status);
    }

    public function test_a_continuity_issue_stays_open_until_confirmed_resolved_known_exception_or_reassigned(): void
    {
        [$hod] = $this->csLeaders();
        [, $employee] = $this->csMember();
        $member = $employee->user;

        $this->actingAs($member)->post("/cs-board/employees/{$employee->id}/continuity-issues", [
            'title' => 'Payments activation stuck',
            'owner' => 'Payments provider',
            'severity' => CsContinuityIssue::SEVERITY_HIGH,
        ])->assertRedirect();

        $issue = CsContinuityIssue::query()->firstOrFail();
        $this->assertSame(CsContinuityIssue::STATUS_OPEN, $issue->status);
        $this->assertFalse($issue->isClosed());

        // Reporting again does not close it — only an explicit close call does.
        $this->assertSame(1, CsContinuityIssue::query()->open()->count());

        // A bare status change with no final outcome is rejected.
        $this->actingAs($hod)->post("/cs-board/continuity-issues/{$issue->id}/close", ['status' => 'resolved'])
            ->assertSessionHasErrors('final_outcome');

        $this->actingAs($hod)->post("/cs-board/continuity-issues/{$issue->id}/close", [
            'status' => 'known_exception', 'final_outcome' => 'Provider confirmed this is expected behaviour.',
        ])->assertRedirect();

        $issue->refresh();
        $this->assertTrue($issue->isClosed());
        $this->assertSame('known_exception', $issue->status);
        $this->assertSame($hod->id, $issue->resolved_by);
        $this->assertNotNull($issue->resolved_at);

        // The owning employee can reopen it if it turns out not to be fixed.
        $this->actingAs($member)->post("/cs-board/continuity-issues/{$issue->id}/reopen", [
            'reason' => 'The issue recurred today.',
        ])->assertRedirect();

        $issue->refresh();
        $this->assertSame(CsContinuityIssue::STATUS_OPEN, $issue->status);
        $this->assertNull($issue->final_outcome);
    }

    public function test_a_deactivated_platform_assignment_keeps_the_date_range_it_applied_for_historical_reconstruction(): void
    {
        [$hod] = $this->csLeaders();
        [, $employee] = $this->csMember();

        $this->actingAs($hod)->post('/cs-board/platform-assignments', [
            'employee_id' => $employee->id, 'country' => 'Kenya', 'effective_from' => '2026-01-01',
        ])->assertRedirect();
        $assignment = CsPlatformAssignment::query()->firstOrFail();

        Carbon::setTestNow('2026-06-01');
        $this->actingAs($hod)->delete("/cs-board/platform-assignments/{$assignment->id}")->assertRedirect();
        Carbon::setTestNow();

        $assignment->refresh();
        $this->assertFalse($assignment->is_active);
        $this->assertSame('2026-06-01', $assignment->effective_to->toDateString());

        // Still reconstructible for a date it actually covered...
        $this->assertSame(1, CsPlatformAssignment::query()->effectiveOn(Carbon::parse('2026-03-01'))->where('id', $assignment->id)->count());
        // ...but not for a date after it was closed out, and no longer "active".
        $this->assertSame(0, CsPlatformAssignment::query()->effectiveOn(Carbon::parse('2026-07-01'))->where('id', $assignment->id)->count());
        $this->assertSame(0, CsPlatformAssignment::query()->active()->where('id', $assignment->id)->count());
    }
}
