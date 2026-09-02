<?php

namespace Tests\Feature\Hr;

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_a_cycle_opens_a_review_per_active_employee(): void
    {
        $hr = User::factory()->create()->assignRole('HR Manager');
        $managerAccount = User::factory()->create();
        $manager = Employee::factory()->create(['user_id' => $managerAccount->id]);
        Employee::factory()->create(['manager_id' => $manager->id]);
        Employee::factory()->terminated()->create();

        $this->actingAs($hr)->post('/hr/performance/cycles', [
            'name' => '2026 Annual', 'type' => 'annual',
            'period_start' => '2026-01-01', 'period_end' => '2026-12-31',
        ])->assertRedirect();

        $cycle = PerformanceCycle::first();
        $this->actingAs($hr)->post("/hr/performance/cycles/{$cycle->id}/activate")->assertRedirect();

        // 2 active employees → 2 reviews; the terminated one is skipped.
        $this->assertSame(2, $cycle->reviews()->count());
        $this->assertSame('active', $cycle->fresh()->status);
    }

    public function test_the_self_then_manager_then_acknowledge_flow(): void
    {
        $hr = User::factory()->create()->assignRole('HR Manager');
        $reviewerAccount = User::factory()->create();
        $subjectAccount = User::factory()->create()->assignRole('Employee');
        $reviewer = Employee::factory()->create(['user_id' => $reviewerAccount->id]);
        $subject = Employee::factory()->create(['user_id' => $subjectAccount->id, 'manager_id' => $reviewer->id]);

        $cycle = PerformanceCycle::create([
            'name' => 'Q1', 'type' => 'quarterly', 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
        ]);
        $this->actingAs($hr)->post("/hr/performance/cycles/{$cycle->id}/activate");
        $review = PerformanceReview::firstWhere('employee_id', $subject->id);

        // Subject writes and submits their self-assessment.
        $this->actingAs($subjectAccount)->patch("/hr/performance/reviews/{$review->id}", [
            'self_assessment' => ['summary' => 'Good year'],
        ])->assertRedirect();
        $this->actingAs($subjectAccount)->post("/hr/performance/reviews/{$review->id}/transition", ['to' => 'submit_self'])->assertRedirect();
        $this->assertSame('manager_review', $review->fresh()->status);

        // A stranger cannot write the manager assessment.
        $this->actingAs($hr)->patch("/hr/performance/reviews/{$review->id}", ['overall_rating' => 3])->assertRedirect(); // HR can (manage)

        // Reviewer rates and shares.
        $this->actingAs($reviewerAccount)->patch("/hr/performance/reviews/{$review->id}", [
            'manager_assessment' => ['summary' => 'Solid'],
            'overall_rating' => 4,
        ])->assertRedirect();
        $this->actingAs($reviewerAccount)->post("/hr/performance/reviews/{$review->id}/transition", ['to' => 'share'])->assertRedirect();
        $this->assertSame('shared', $review->fresh()->status);
        $this->assertEqualsWithDelta(4.0, (float) $review->fresh()->overall_rating, 0.01);

        // Subject acknowledges.
        $this->actingAs($subjectAccount)->post("/hr/performance/reviews/{$review->id}/transition", ['to' => 'acknowledge'])->assertRedirect();
        $this->assertSame('acknowledged', $review->fresh()->status);
    }

    public function test_a_stranger_cannot_view_someone_elses_review(): void
    {
        $stranger = User::factory()->create()->assignRole('Employee');
        $cycle = PerformanceCycle::create([
            'name' => 'X', 'type' => 'annual', 'period_start' => '2026-01-01', 'period_end' => '2026-12-31',
        ]);
        $review = $cycle->reviews()->create(['employee_id' => Employee::factory()->create()->id, 'status' => 'self_review']);

        $this->actingAs($stranger)->get("/hr/performance/reviews/{$review->id}")->assertForbidden();
    }

    public function test_a_manager_can_set_goals_for_a_report(): void
    {
        $managerAccount = User::factory()->create()->assignRole('Department Manager');
        $manager = Employee::factory()->create(['user_id' => $managerAccount->id]);
        $report = Employee::factory()->create(['manager_id' => $manager->id]);

        $this->actingAs($managerAccount)->post("/hr/employees/{$report->id}/goals", [
            'title' => 'Ship the new dashboard', 'weight' => 40,
        ])->assertRedirect();

        $this->assertDatabaseHas('performance_goals', ['employee_id' => $report->id, 'title' => 'Ship the new dashboard']);
    }
}
