<?php

namespace Tests\Feature\WordPress;

use App\Models\User;
use App\Models\WordPressSite;
use App\Models\WordPressUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPressUserIndexTest extends TestCase
{
    use RefreshDatabase;

    private function ceo(): User
    {
        return User::factory()->create()->assignRole('CEO');
    }

    public function test_users_can_be_sorted_by_an_allowed_column()
    {
        $site = WordPressSite::factory()->create();

        WordPressUser::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_user_id' => 1,
            'username' => 'charlie',
            'email' => 'charlie@exotic-online.com',
            'roles' => ['editor'],
            'synced_at' => now(),
        ]);
        WordPressUser::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_user_id' => 2,
            'username' => 'alice',
            'email' => 'alice@exotic-online.com',
            'roles' => ['editor'],
            'synced_at' => now(),
        ]);

        $props = $this->actingAs($this->ceo())
            ->get('/admin/wordpress-users?sort=username&direction=asc')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(['alice', 'charlie'], collect($props['users']['data'])->pluck('username')->all());
        $this->assertSame('username', $props['sort']);
    }

    public function test_an_unrecognized_sort_column_is_ignored()
    {
        $site = WordPressSite::factory()->create();
        WordPressUser::query()->create([
            'wordpress_site_id' => $site->id,
            'wp_user_id' => 1,
            'username' => 'alice',
            'roles' => [],
            'synced_at' => now(),
        ]);

        $props = $this->actingAs($this->ceo())
            ->get('/admin/wordpress-users?sort=roles&direction=asc')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNull($props['sort']);
    }
}
