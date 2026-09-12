<?php

namespace Tests\Feature\WordPress;

use App\Models\User;
use App\Models\WordPressSite;
use App\Models\WordPressUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPressUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function seedUsers(int $count): void
    {
        $site = WordPressSite::factory()->create();
        for ($i = 0; $i < $count; $i++) {
            WordPressUser::query()->create([
                'wordpress_site_id' => $site->id,
                'wp_user_id' => $i + 1,
                'username' => "user{$i}",
                'email' => "user{$i}@example.com",
                'roles' => ['editor'],
                'synced_at' => now(),
            ]);
        }
    }

    public function test_defaults_to_20_per_page(): void
    {
        $this->seedUsers(25);
        $admin = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($admin)->get('/admin/wordpress-users');

        $response->assertInertia(fn ($page) => $page
            ->where('perPage', 20)
            ->where('users.data', fn ($data) => count($data) === 20)
            ->where('users.total', 25));
    }

    public function test_per_page_accepts_only_the_pickers_own_values(): void
    {
        $this->seedUsers(5);
        $admin = User::factory()->create()->assignRole('CEO');

        $this->actingAs($admin)->get('/admin/wordpress-users?per_page=100')
            ->assertInertia(fn ($page) => $page->where('perPage', 100));

        // An arbitrary value outside {20,50,100,200,All} silently falls back
        // to the default rather than letting a crafted query string request
        // an unbounded page size.
        $this->actingAs($admin)->get('/admin/wordpress-users?per_page=999999')
            ->assertInertia(fn ($page) => $page->where('perPage', 20));
    }

    public function test_all_maps_to_a_bounded_ceiling_not_truly_unbounded(): void
    {
        $this->seedUsers(3);
        $admin = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($admin)->get('/admin/wordpress-users?per_page=All');

        $response->assertInertia(fn ($page) => $page
            ->where('perPage', 5000)
            ->where('users.data', fn ($data) => count($data) === 3));
    }
}
