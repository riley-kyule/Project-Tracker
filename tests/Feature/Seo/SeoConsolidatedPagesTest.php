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
        $user = User::factory()->create()->assignRole('Employee');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);

        $this->actingAs($user)->get('/seo-board')->assertInertia(fn ($page) => $page
            ->where('dailyCard.status', 'open')
            ->where('weeklyCard', null)
            ->has('history'));

        $this->assertDatabaseCount('seo_daily_cards', 1);
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
