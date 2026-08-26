<?php

namespace Tests\Feature\WordPress;

use App\Models\User;
use App\Models\WordPressSite;
use App\Models\WordPressUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoDashboardWordPressWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_staff_domain_users_appear_in_the_dashboard_widget()
    {
        $site = WordPressSite::factory()->create();
        WordPressUser::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_user_id' => 1,
            'username' => 'staffer',
            'email' => 'staffer@exotic-online.com',
            'roles' => ['administrator'],
            'synced_at' => now(),
        ]);
        WordPressUser::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_user_id' => 2,
            'username' => 'customer',
            'email' => 'customer@example.com',
            'roles' => ['subscriber'],
            'synced_at' => now(),
        ]);

        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->get('/dashboards/ceo')->assertOk();
        $staff = $response->viewData('page')['props']['wordpressStaff'];

        $this->assertCount(1, $staff);
        $this->assertSame('staffer@exotic-online.com', $staff[0]['email']);
    }

    public function test_employees_cannot_view_the_ceo_dashboard()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/dashboards/ceo')->assertForbidden();
    }
}
