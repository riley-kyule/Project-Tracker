<?php

namespace Tests\Feature\Seo;

use App\Models\Board;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The three consolidated hubs (My SEO Board / SEO Board HOD / SEO Board Settings) that replaced eight separate pages. */
class SeoConsolidatedPagesTest extends TestCase
{
    use RefreshDatabase;

    private function seoDepartment(): Department
    {
        return Department::query()->where('slug', 'seo')->firstOrFail();
    }

    /** /seo-board is now just a redirect to the SEO department's Kanban board, which carries the Score Based tab — see boards/show.tsx and SeoEmployeeBoardData. */
    public function test_seo_board_redirects_to_the_departments_kanban_board(): void
    {
        $department = $this->seoDepartment();
        $user = User::factory()->create(['department_id' => $department->id])->assignRole('Marketing');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
        $kanban = Board::factory()->create(['department_id' => $department->id, 'name' => 'SEO Board']);

        $this->actingAs($user)->get('/seo-board')->assertRedirect("/boards/{$kanban->id}");
    }

    /**
     * The weighted scoring system now lives as a tab on the existing Kanban
     * board, not a separate page, so the auto-provisioning and history data
     * are checked directly against that board's own response.
     */
    public function test_the_kanban_board_carries_the_score_based_tabs_data_and_auto_provisions_todays_card(): void
    {
        $department = $this->seoDepartment();
        // User::department_id, not just Employee::department_id — see
        // User::isSeoEmployee() and the identical note elsewhere in the SEO
        // Board (SeoHodPanelData::forDepartment(), SeoEmployeeBoardData):
        // that's the authoritative "which team do you actually work in" field.
        $user = User::factory()->create(['department_id' => $department->id])->assignRole('Marketing');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
        $kanban = Board::factory()->create(['department_id' => $department->id, 'name' => 'SEO Board']);

        $this->actingAs($user)->get("/boards/{$kanban->id}")->assertInertia(fn ($page) => $page
            ->where('seoScoreBoard.dailyCard.status', 'open')
            ->where('seoScoreBoard.weeklyCard', null)
            ->has('seoScoreBoard.history'));

        $this->assertDatabaseCount('seo_daily_cards', 1);
    }

    /**
     * The actual production incident this guards against: seo.cards.view is
     * granted at the role level (Marketing), and Marketing has members who
     * aren't on the SEO team (e.g. Social Media). Before User::isSeoEmployee()
     * existed, visiting /seo-board silently provisioned a card under their
     * own (non-SEO) department, and that department's HOD ended up on the
     * midnight report's recipient list for work that was never SEO work.
     */
    public function test_a_marketing_role_user_outside_the_seo_department_cannot_provision_a_card(): void
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $user = User::factory()->create(['department_id' => $marketing->id])->assignRole('Marketing');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $marketing->id]);
        $seoKanban = Board::factory()->create(['department_id' => $this->seoDepartment()->id]);

        $this->assertTrue($user->can('seo.cards.view'), 'sanity check: still holds the permission at the role level');

        $this->actingAs($user)->get('/seo-board')->assertNotFound();

        // Also confirmed from the other side: even opening the real SEO Board
        // directly (they can view it, it's visibility=company/department),
        // no Score Based data ever comes back for them.
        $this->actingAs($user)->get("/boards/{$seoKanban->id}")->assertInertia(fn ($page) => $page
            ->where('seoScoreBoard', null));

        $this->assertDatabaseCount('seo_daily_cards', 0);
    }

    public function test_seo_board_settings_shows_only_the_tabs_the_user_can_manage(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/seo-board/settings')->assertInertia(fn ($page) => $page
            ->where('can.templates', true)
            ->where('can.notifications', true)
            ->has('templates')
            ->has('recipients'));

        $marketing = User::factory()->create()->assignRole('Marketing');
        $this->actingAs($marketing)->get('/seo-board/settings')->assertForbidden();
    }
}
