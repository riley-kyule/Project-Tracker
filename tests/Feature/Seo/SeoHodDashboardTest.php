<?php

namespace Tests\Feature\Seo;

use App\Models\Department;
use App\Models\Employee;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO Board Requirements Specification v1.1 §11.2/§13 "HOD dashboard"
 * acceptance test. The HOD view is a section on the existing "My Department"
 * dashboard (DashboardController::department), not a standalone page — an
 * HOD already has one department home, per the ease-of-use simplification.
 */
class SeoHodDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function seoDepartment(): Department
    {
        return Department::query()->where('slug', 'seo')->firstOrFail();
    }

    public function test_my_department_shows_the_seo_board_section_for_a_marketing_hod(): void
    {
        $department = $this->seoDepartment();
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $hod = User::factory()->create(['department_id' => $marketing->id])->assignRole('Department Manager');
        $marketing->update(['manager_id' => $hod->id]);

        $employeeUser = User::factory()->create()->assignRole('Employee');
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id, 'department_id' => $department->id]);
        SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'work_date' => today(), 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ])->items()->create(['section' => 'monitoring', 'name' => 'A', 'weight' => 100, 'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => 1]);

        // Marketing itself has no SEO cards — the section resolves to SEO, its
        // child, not the department actually being viewed.
        $this->actingAs($hod)->get('/dashboards/department')->assertInertia(fn ($page) => $page
            ->where('department.id', $marketing->id)
            ->where('seoBoard.department.id', $department->id)
            ->has('seoBoard.today', 1));
    }

    /**
     * A real production-data gap: an employee's HR record can legitimately
     * sit under a parent department (e.g. Marketing) for org-chart reasons
     * while their platform login (User::department_id) is the actual SEO
     * sub-department. The roster must follow the login, not the HR field.
     */
    public function test_the_employee_roster_follows_the_users_department_not_the_hr_records(): void
    {
        $department = $this->seoDepartment();
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $hod = User::factory()->create(['department_id' => $marketing->id])->assignRole('Department Manager');
        $marketing->update(['manager_id' => $hod->id]);

        $employeeUser = User::factory()->create(['department_id' => $department->id])->assignRole('Employee');
        // Filed under Marketing in HR on purpose — this must not hide them from the SEO roster.
        Employee::factory()->create(['user_id' => $employeeUser->id, 'department_id' => $marketing->id]);

        $this->actingAs($hod)->get('/dashboards/department')->assertInertia(fn ($page) => $page
            ->has('seoBoard.employees', 1));
    }

    public function test_my_department_has_no_seo_section_for_an_unrelated_department(): void
    {
        $it = Department::query()->where('slug', 'it')->firstOrFail();
        $manager = User::factory()->create(['department_id' => $it->id])->assignRole('Department Manager');
        $it->update(['manager_id' => $manager->id]);

        $this->actingAs($manager)->get('/dashboards/department')->assertInertia(fn ($page) => $page
            ->where('department.id', $it->id)
            ->where('seoBoard', null));
    }

    public function test_ceo_sees_the_seo_section_when_switching_to_seo_but_not_to_an_unrelated_department(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $seo = $this->seoDepartment();
        $it = Department::query()->where('slug', 'it')->firstOrFail();

        $this->actingAs($ceo)->get("/dashboards/department?department_id={$seo->id}")->assertInertia(fn ($page) => $page
            ->where('seoBoard.department.id', $seo->id));

        $this->actingAs($ceo)->get("/dashboards/department?department_id={$it->id}")->assertInertia(fn ($page) => $page
            ->where('seoBoard', null));
    }

    public function test_ceo_reaches_the_seo_board_via_the_stable_redirect_link(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $seo = $this->seoDepartment();

        $this->actingAs($ceo)->get('/seo-board/hod')
            ->assertRedirect("/dashboards/department?department_id={$seo->id}");
    }

    public function test_an_ordinary_employee_cannot_open_my_department_at_all(): void
    {
        $it = Department::query()->where('slug', 'it')->firstOrFail();
        $employee = User::factory()->create(['department_id' => $it->id])->assignRole('Employee');

        $this->actingAs($employee)->get('/dashboards/department')->assertForbidden();
    }
}
