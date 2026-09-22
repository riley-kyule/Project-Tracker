<?php

namespace Tests\Feature\Seo;

use App\Models\Department;
use App\Models\Employee;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\User;
use App\Services\Seo\SeoScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO Board Requirements Specification v1.1 §11 "Output quantities by task
 * type" and "comparison among employees ... with task mix visible", and
 * §11.2 "Exports: authorised export of the selected employee, department and
 * date range" — the three dashboard/report gaps closed after the initial
 * build.
 */
class SeoExportAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function seoDepartment(): Department
    {
        return Department::query()->where('slug', 'seo')->firstOrFail();
    }

    /** Leads Marketing, which SEO sits under — mirrors SeoHodDashboardTest's setup exactly, since these tests hit the dashboard route, which (unlike the item-level POST routes) also needs $user->department_id set to resolve $user->department. */
    private function hodFor(Department $department): User
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $hod = User::factory()->create(['department_id' => $marketing->id])->assignRole('Department Manager');
        $marketing->update(['manager_id' => $hod->id]);

        return $hod;
    }

    private function seoEmployee(Department $department): Employee
    {
        $user = User::factory()->create(['department_id' => $department->id])->assignRole('Employee');

        return Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
    }

    /** A daily card with one decided, quantity-based production item — the shape both the export and the dashboard rollups read from. */
    private function decidedProductionItem(Employee $employee, Department $department): SeoCardItem
    {
        $card = SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'work_date' => today(), 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);

        $item = $card->items()->create([
            'section' => 'production', 'name' => 'Content execution', 'classification' => 'production',
            'weight' => 20, 'target_quantity' => 4, 'achieved_quantity' => 4, 'quantity_unit' => 'articles',
            'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_SUBMITTED, 'position' => 1,
        ]);

        app(SeoScoringService::class)->decide($item, SeoCardItem::DECISION_APPROVED, null, User::factory()->create());

        return $item->fresh();
    }

    public function test_output_by_task_type_totals_quantities_across_cards(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $this->decidedProductionItem($employee, $department);

        $this->actingAs($hod)->get('/dashboards/department')->assertInertia(fn ($page) => $page
            ->where('seoBoard.outputByTaskType.0.name', 'Content execution')
            // Whole-number floats cross the wire as JSON integers (no decimal point), so these compare against plain ints.
            ->where('seoBoard.outputByTaskType.0.total_target_quantity', 4)
            ->where('seoBoard.outputByTaskType.0.total_achieved_quantity', 4)
            ->where('seoBoard.outputByTaskType.0.avg_completion_factor', 100));
    }

    public function test_task_mix_breaks_down_earned_points_by_section(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $this->decidedProductionItem($employee, $department);

        $this->actingAs($hod)->get('/dashboards/department')->assertInertia(fn ($page) => $page
            ->has('seoBoard.taskMix.employees', 1)
            ->where('seoBoard.taskMix.employees.0.employee_id', $employee->id)
            // Weight 20 x 100% approved = 20 earned points, all in the "production" section.
            ->where('seoBoard.taskMix.employees.0.by_section.production', 20)
            ->where('seoBoard.taskMix.employees.0.total_points', 20));
    }

    public function test_hod_can_export_one_employees_data_as_csv(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $this->decidedProductionItem($employee, $department);

        $from = today()->toDateString();
        $to = today()->toDateString();

        $response = $this->actingAs($hod)
            ->get("/seo-board/hod/export/employee?employee_id={$employee->id}&from={$from}&to={$to}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Content execution', $csv);
        $this->assertStringContainsString('approved', $csv);
    }

    public function test_hod_can_export_the_whole_department_as_csv(): void
    {
        $department = $this->seoDepartment();
        $hod = $this->hodFor($department);
        $employee = $this->seoEmployee($department);
        $this->decidedProductionItem($employee, $department);

        $from = today()->toDateString();
        $to = today()->toDateString();

        $response = $this->actingAs($hod)->get("/seo-board/hod/export/department?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertStringContainsString('Content execution', $response->streamedContent());
    }

    /**
     * A "Department Manager" role carries seo.cards.approve globally (so a
     * lead can run their own department's SEO Board without a separate grant
     * — see RoleSeeder), so the block here isn't the permission check, it's
     * that this manager's own department (IT) resolves to no SEO board at
     * all, the same 404 DashboardController::department gives for the
     * identical case. A non-CEO/Admin can't redirect this at another
     * department via department_id either — that query param is only ever
     * honoured for CEO/Administrator.
     */
    public function test_export_404s_for_a_manager_whose_own_department_has_no_seo_board(): void
    {
        $it = Department::query()->where('slug', 'it')->firstOrFail();
        $manager = User::factory()->create(['department_id' => $it->id])->assignRole('Department Manager');
        $it->update(['manager_id' => $manager->id]);

        $this->actingAs($manager)
            ->get("/seo-board/hod/export/department?department_id={$this->seoDepartment()->id}&from=2026-01-01&to=2026-01-07")
            ->assertNotFound();
    }

    public function test_export_is_forbidden_without_seo_cards_approve_permission(): void
    {
        $department = $this->seoDepartment();
        // Leads the department but was never granted the SEO Board permission itself.
        $lead = User::factory()->create(['department_id' => $department->id]);
        $department->update(['manager_id' => $lead->id]);

        $this->actingAs($lead)
            ->get('/seo-board/hod/export/department?from=2026-01-01&to=2026-01-07')
            ->assertForbidden();
    }
}
