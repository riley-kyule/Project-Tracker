<?php

namespace Tests\Feature\Seo;

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

    public function test_my_seo_board_auto_provisions_todays_card_and_renders_history(): void
    {
        $department = $this->seoDepartment();
        // User::department_id, not just Employee::department_id — see
        // User::isSeoEmployee() and the identical note elsewhere in the SEO
        // Board (SeoHodPanelData::forDepartment(), SeoBoardController::mine()):
        // that's the authoritative "which team do you actually work in" field.
        $user = User::factory()->create(['department_id' => $department->id])->assignRole('Marketing');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        $this->actingAs($user)->get('/seo-board')->assertInertia(fn ($page) => $page
            ->where('dailyCard.status', 'open')
            ->where('weeklyCard', null)
            ->has('history'));

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

        $this->assertTrue($user->can('seo.cards.view'), 'sanity check: still holds the permission at the role level');

        $this->actingAs($user)->get('/seo-board')->assertNotFound();

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
