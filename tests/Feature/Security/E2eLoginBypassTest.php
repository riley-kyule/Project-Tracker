<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The e2e login bypass (routes/e2e.php, E2eAuthController) exists purely
 * for the Playwright suite, which has no real OAuth flow to drive. This
 * locks in the default posture: with ALLOW_E2E_LOGIN unset (the default
 * everywhere except the e2e suite's own throwaway environment), the route
 * doesn't exist at all.
 */
class E2eLoginBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bypass_route_does_not_exist_by_default()
    {
        $user = User::factory()->create()->assignRole('Employee');

        $this->post('/_e2e/login', ['email' => $user->email])->assertNotFound();
    }
}
