<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PHPUnit runs trip VerifyCsrfToken's own runningUnitTests() bypass, so
     * every other feature test posts without a token by design. Flipping the
     * bound env away from "testing" for just this request removes that
     * bypass and exercises the real check.
     */
    public function test_a_web_mutation_without_a_valid_csrf_token_is_rejected()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->actingAs($user)
                ->withHeaders(['X-CSRF-TOKEN' => 'not-a-real-token'])
                ->post('/notifications/read-all')
                ->assertStatus(419);
        } finally {
            $this->app['env'] = $original;
        }
    }
}
