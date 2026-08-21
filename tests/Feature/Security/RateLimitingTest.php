<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_429_after_the_configured_ceiling()
    {
        $user = User::factory()->create()->assignRole('Employee');

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->get('/search')->assertOk();
        }

        $this->actingAs($user)->get('/search')->assertStatus(429);
    }
}
