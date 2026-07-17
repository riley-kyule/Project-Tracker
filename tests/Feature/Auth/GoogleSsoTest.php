<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleSsoTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $email, string $name = 'Google User', string $id = 'google-123'): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getId')->andReturn($id);

        $provider = Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_is_disabled_when_google_is_not_configured()
    {
        config(['services.google.client_id' => null]);

        $this->get('/auth/google/redirect')->assertNotFound();
    }

    public function test_callback_rejects_a_non_company_domain()
    {
        config(['services.google.client_id' => 'test-id', 'services.google.allowed_domains' => ['ewms.test']]);
        $this->fakeGoogleUser('someone@gmail.com');

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'someone@gmail.com']);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_failed',
            'auditable_type' => null,
            'auditable_id' => null,
            'new_values->email' => 'someone@gmail.com',
        ]);
    }

    public function test_domain_rejection_for_a_known_user_is_audited_against_that_user()
    {
        config(['services.google.client_id' => 'test-id', 'services.google.allowed_domains' => ['ewms.test']]);
        $user = User::factory()->create(['email' => 'existing@ewms.test']);
        $this->fakeGoogleUser('existing@ewms.test', id: 'google-should-not-link');

        config(['services.google.allowed_domains' => ['other.test']]);
        $this->get('/auth/google/callback')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_failed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_callback_logs_in_an_existing_user_and_links_google_id()
    {
        config(['services.google.client_id' => 'test-id', 'services.google.allowed_domains' => ['ewms.test']]);
        $user = User::factory()->create(['email' => 'existing@ewms.test', 'google_id' => null]);
        $this->fakeGoogleUser('existing@ewms.test', id: 'google-999');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-999', $user->fresh()->google_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_succeeded',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_callback_provisions_a_bare_account_for_a_new_company_user()
    {
        config(['services.google.client_id' => 'test-id', 'services.google.allowed_domains' => ['ewms.test']]);
        $this->fakeGoogleUser('newperson@ewms.test', 'New Person');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'newperson@ewms.test')->firstOrFail();
        $this->assertSame('New Person', $user->name);
        $this->assertNull($user->password);
        $this->assertTrue($user->getRoleNames()->isEmpty());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'event' => 'created',
        ]);
    }

    public function test_callback_rejects_an_inactive_users_google_sign_in()
    {
        config(['services.google.client_id' => 'test-id', 'services.google.allowed_domains' => ['ewms.test']]);
        $user = User::factory()->create(['email' => 'suspended@ewms.test', 'status' => User::STATUS_SUSPENDED]);
        $this->fakeGoogleUser('suspended@ewms.test');

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_blocked_inactive',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }
}
