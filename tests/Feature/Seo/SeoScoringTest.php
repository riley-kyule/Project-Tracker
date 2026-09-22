<?php

namespace Tests\Feature\Seo;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\SeoFinalScore;
use App\Models\SeoTaskTemplate;
use App\Models\User;
use App\Services\Seo\SeoCardLifecycleService;
use App\Services\Seo\SeoScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers most of the SEO Board Requirements Specification v1.1 §13
 * acceptance table: card totals, weighted (not equal-share) progress,
 * no self-scoring, evidence requirements, conditional-work normalisation,
 * the 70/30 final score formula, and the audit trail.
 */
class SeoScoringTest extends TestCase
{
    use RefreshDatabase;

    private function seoDepartment(): Department
    {
        return Department::query()->where('slug', 'seo')->firstOrFail();
    }

    private function hodFor(Department $department): User
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $hod = User::factory()->create()->assignRole('Department Manager');
        $marketing->update(['manager_id' => $hod->id]);

        return $hod;
    }

    private function seoEmployee(Department $department): Employee
    {
        $user = User::factory()->create()->assignRole('Employee');

        return Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
    }

    private function cardWithMonitoringItems(Employee $employee, Department $department): SeoDailyCard
    {
        $card = SeoDailyCard::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'work_date' => today(),
            'status' => SeoDailyCard::STATUS_OPEN,
            'planned_points' => 100,
        ]);

        // Mirrors §4.3 exactly: 3 + 4 + 4 + 4 = 15, plus the rest of the card to reach 100.
        $weights = ['Site availability check' => 3, 'Site health and security check' => 4, 'Ranking and SERP monitoring' => 4, 'Traffic performance monitoring' => 4, 'Implementation and follow-up' => 10, 'Document completed work' => 5, 'Report issues and blockers' => 5];
        $weights['Content execution'] = 65; // fills the remaining production-work points

        foreach ($weights as $name => $weight) {
            $card->items()->create([
                'section' => 'monitoring', 'name' => $name, 'classification' => 'mandatory',
                'weight' => $weight, 'evidence_required' => true, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => 1,
            ]);
        }

        return $card;
    }

    public function test_card_totals_must_equal_exactly_100(): void
    {
        $department = $this->seoDepartment();
        $employee = $this->seoEmployee($department);
        $card = $this->cardWithMonitoringItems($employee, $department);

        $scoring = app(SeoScoringService::class);

        $this->assertTrue($scoring->totalsExactly100($card));
    }

    public function test_assigning_daily_production_items_that_do_not_reach_100_is_rejected(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $card = app(SeoCardLifecycleService::class)->createNextCard($employee, $department->id, today()->subDay());

        $this->assertSame(35.0, (float) $card->items()->sum('weight')); // monitoring 15 + implementation 10 + documentation 10

        $template = SeoTaskTemplate::query()->forCardType(SeoTaskTemplate::CARD_TYPE_DAILY)
            ->where('classification', SeoTaskTemplate::CLASSIFICATION_PRODUCTION)->where('name', 'Content execution')->firstOrFail();

        $this->actingAs($hod)->post("/seo-board/hod/daily/{$card->id}/assign-items", [
            'items' => [['template_id' => $template->id, 'weight' => 20]], // within its own 10-25 band, but 35 + 20 = 55, not 100
        ])->assertSessionHasErrors('items');

        $this->assertSame(35.0, (float) $card->fresh()->items()->sum('weight'));
    }

    public function test_assigning_daily_production_items_that_reach_100_succeeds(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $card = app(SeoCardLifecycleService::class)->createNextCard($employee, $department->id, today()->subDay());

        $templates = SeoTaskTemplate::query()->forCardType(SeoTaskTemplate::CARD_TYPE_DAILY)
            ->where('classification', SeoTaskTemplate::CLASSIFICATION_PRODUCTION)
            ->whereIn('name', ['Content execution', 'Improve underperforming landing pages', 'On-page optimisation'])
            ->get()->keyBy('name');

        // 35 fixed + 25 + 25 + 15 = 100, each weight within its own template's band.
        $response = $this->actingAs($hod)->post("/seo-board/hod/daily/{$card->id}/assign-items", [
            'items' => [
                ['template_id' => $templates['Content execution']->id, 'weight' => 25],
                ['template_id' => $templates['Improve underperforming landing pages']->id, 'weight' => 25],
                ['template_id' => $templates['On-page optimisation']->id, 'weight' => 15],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(100.0, (float) $card->fresh()->items()->sum('weight'));
    }

    /** §4.4/§5.2 — production work is often "work this specific URL by this time," neither of which the fixed template library can capture on its own. */
    public function test_assigning_a_production_item_records_its_assigned_url_and_due_time(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $card = app(SeoCardLifecycleService::class)->createNextCard($employee, $department->id, today()->subDay());

        $templates = SeoTaskTemplate::query()->forCardType(SeoTaskTemplate::CARD_TYPE_DAILY)
            ->where('classification', SeoTaskTemplate::CLASSIFICATION_PRODUCTION)
            ->whereIn('name', ['Content execution', 'Improve underperforming landing pages', 'On-page optimisation'])
            ->get()->keyBy('name');

        $response = $this->actingAs($hod)->post("/seo-board/hod/daily/{$card->id}/assign-items", [
            'items' => [
                ['template_id' => $templates['Content execution']->id, 'weight' => 25, 'assigned_url' => 'https://example.com/blog/post', 'due_time' => '14:30'],
                ['template_id' => $templates['Improve underperforming landing pages']->id, 'weight' => 25],
                ['template_id' => $templates['On-page optimisation']->id, 'weight' => 15],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $item = $card->fresh()->items()->where('template_id', $templates['Content execution']->id)->firstOrFail();
        $this->assertSame('https://example.com/blog/post', $item->assigned_url);
        $this->assertStringStartsWith('14:30', $item->due_time);
    }

    public function test_assigning_a_production_item_outside_its_templates_band_is_rejected(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $card = app(SeoCardLifecycleService::class)->createNextCard($employee, $department->id, today()->subDay());

        // Keyword opportunity research's band is 5-15 per the seeder.
        $template = SeoTaskTemplate::query()->forCardType(SeoTaskTemplate::CARD_TYPE_DAILY)
            ->where('classification', SeoTaskTemplate::CLASSIFICATION_PRODUCTION)->where('name', 'Keyword opportunity research')->firstOrFail();

        $this->actingAs($hod)->post("/seo-board/hod/daily/{$card->id}/assign-items", [
            'items' => [['template_id' => $template->id, 'weight' => 65]], // way outside 5-15
        ])->assertSessionHasErrors('items');
    }

    public function test_weighted_items_score_their_actual_points_not_an_equal_share(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $card = $this->cardWithMonitoringItems($employee, $department);
        $scoring = app(SeoScoringService::class);

        $monitoring = $card->items()->whereIn('name', ['Site availability check', 'Site health and security check', 'Ranking and SERP monitoring', 'Traffic performance monitoring'])->get();

        $this->actingAs($hod);
        foreach ($monitoring as $item) {
            $scoring->decide($item, SeoCardItem::DECISION_APPROVED, null, $hod);
        }

        // 3 + 4 + 4 + 4 = 15 actual points, never "4 / 18" equal shares (§13 "Weighted progress").
        $this->assertSame(15.0, (float) $monitoring->fresh()->sum('earned_points'));
    }

    public function test_employee_submission_alone_never_creates_approved_points(): void
    {
        $department = $this->seoDepartment();
        $employee = $this->seoEmployee($department);
        $card = $this->cardWithMonitoringItems($employee, $department);
        $item = $card->items()->first();
        $item->update(['evidence_required' => false]); // isolates this test to the no-self-scoring rule — evidence is covered separately below.

        $this->actingAs($employee->user)->post("/seo-board/items/{$item->id}/status", [
            'employee_status' => SeoCardItem::STATUS_SUBMITTED,
        ])->assertRedirect();

        $card->refresh();
        $this->assertNull($card->approved_points);
    }

    public function test_evidence_is_required_before_an_item_can_be_submitted(): void
    {
        $department = $this->seoDepartment();
        $employee = $this->seoEmployee($department);
        $card = $this->cardWithMonitoringItems($employee, $department);
        $item = $card->items()->where('evidence_required', true)->first();

        $this->actingAs($employee->user)->post("/seo-board/items/{$item->id}/status", [
            'employee_status' => SeoCardItem::STATUS_SUBMITTED,
        ])->assertSessionHasErrors('employee_status');

        $item->refresh();
        $this->assertSame(SeoCardItem::STATUS_NOT_STARTED, $item->employee_status);
    }

    public function test_hod_approval_is_required_before_points_are_earned(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $card = $this->cardWithMonitoringItems($employee, $department);
        $item = $card->items()->first();

        $scoring = app(SeoScoringService::class);
        $this->assertNull($item->earned_points);

        $this->actingAs($hod);
        $scoring->decide($item, SeoCardItem::DECISION_APPROVED, null, $hod);

        $item->refresh();
        $this->assertEquals((float) $item->weight, (float) $item->earned_points);
    }

    public function test_exempted_items_are_excluded_from_the_denominator_and_the_rest_normalises_to_100(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);

        $card = SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'work_date' => today(), 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);
        $a = $card->items()->create(['section' => 'production', 'name' => 'A', 'weight' => 50, 'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => 1]);
        $b = $card->items()->create(['section' => 'production', 'name' => 'B', 'weight' => 50, 'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => 2]);

        $scoring = app(SeoScoringService::class);
        $this->actingAs($hod);

        // B had no qualifying work available — HOD exempts it (§10), A is approved in full.
        $scoring->decide($b, SeoCardItem::DECISION_EXEMPTED, 'No qualifying work existed this period.', $hod);
        $scoring->decide($a, SeoCardItem::DECISION_APPROVED, null, $hod);

        $card->refresh();
        // Denominator is now just A's 50 points; A's 50 earned points / 50 denominator x 100 = 100.
        $this->assertSame(100.0, (float) $card->approved_points);
    }

    public function test_weight_changes_after_assignment_require_a_reason(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $card = $this->cardWithMonitoringItems($employee, $department);
        $item = $card->items()->first();

        $this->actingAs($hod);

        $scoring = app(SeoScoringService::class);

        $this->expectException(\InvalidArgumentException::class);
        $scoring->updateWeight($item, 20.0, '');
    }

    public function test_weight_change_is_recorded_in_the_audit_log(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $card = $this->cardWithMonitoringItems($employee, $department);
        $item = $card->items()->first();

        $this->actingAs($hod);
        app(SeoScoringService::class)->updateWeight($item, 6.0, 'Corrected to match the assigned scope.');

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => (new SeoCardItem)->getMorphClass(),
            'auditable_id' => $item->id,
            'event' => 'seo_item.weight_changed',
            'actor_id' => $hod->id,
        ]);
    }

    public function test_final_score_applies_the_70_30_formula(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $scoring = app(SeoScoringService::class);
        $this->actingAs($hod);

        $weekStart = today()->startOfWeek();

        // One daily card approved at 80 points.
        $card = SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'work_date' => $weekStart, 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);
        $item = $card->items()->create(['section' => 'production', 'name' => 'X', 'weight' => 100, 'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => 1]);
        $scoring->decide($item, SeoCardItem::DECISION_MAJOR_REWORK, 'Needed significant rework.', $hod); // 50%

        // Weekly card approved at 90 points.
        $weekly = \App\Models\SeoWeeklyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'week_start_date' => $weekStart, 'week_end_date' => $weekStart->copy()->endOfWeek(),
            'status' => \App\Models\SeoWeeklyCard::STATUS_PLAN_APPROVED, 'planned_points' => 100,
        ]);
        $weeklyItem = $weekly->items()->create(['section' => 'deliverables', 'name' => 'Y', 'weight' => 100, 'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => 1]);
        $scoring->decide($weeklyItem, SeoCardItem::DECISION_MINOR_CORRECTION, 'Minor fix needed.', $hod); // 75%

        $scoring->recalculateFinalScore($employee, $weekStart);

        $final = SeoFinalScore::query()->where('employee_id', $employee->id)->whereDate('week_start_date', $weekStart->toDateString())->firstOrFail();

        // (50 x 70%) + (75 x 30%) = 35 + 22.5 = 57.5
        $this->assertSame(57.5, (float) $final->final_score);
    }
}
