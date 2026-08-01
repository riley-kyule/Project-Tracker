<?php

namespace Tests\Feature\Settings;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleDriveIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $email, ?string $refreshToken = 'refresh-token-123'): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->token = 'access-token-123';
        $socialiteUser->refreshToken = $refreshToken;
        $socialiteUser->expiresIn = 3600;

        $provider = Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnSelf();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_only_administrators_or_ceo_can_connect()
    {
        config(['services.google.client_id' => 'test-id']);
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/settings/integrations/google-drive/connect')->assertForbidden();
    }

    public function test_callback_stores_tokens_and_connected_email()
    {
        config(['services.google.client_id' => 'test-id', 'services.google.allowed_domains' => ['ewms.test']]);
        $admin = User::factory()->create()->assignRole('Administrator');
        $this->fakeGoogleUser('backups@ewms.test');

        $this->actingAs($admin)
            ->get('/settings/integrations/google-drive/callback')
            ->assertRedirect(route('integrations.edit'));

        $settings = CompanySetting::current();
        $this->assertSame('backups@ewms.test', $settings->google_drive_connected_email);
        $this->assertSame('access-token-123', $settings->google_drive_access_token);
        $this->assertSame('refresh-token-123', $settings->google_drive_refresh_token);
        $this->assertDatabaseHas('audit_logs', ['event' => 'google_drive_connected']);
    }

    public function test_callback_rejects_a_non_company_domain()
    {
        config(['services.google.client_id' => 'test-id', 'services.google.allowed_domains' => ['ewms.test']]);
        $admin = User::factory()->create()->assignRole('Administrator');
        $this->fakeGoogleUser('someone@gmail.com');

        $this->actingAs($admin)
            ->get('/settings/integrations/google-drive/callback')
            ->assertRedirect(route('integrations.edit'))
            ->assertSessionHasErrors('drive');

        $this->assertNull(CompanySetting::current()->google_drive_connected_email);
    }

    public function test_callback_rejects_a_missing_refresh_token()
    {
        config(['services.google.client_id' => 'test-id', 'services.google.allowed_domains' => ['ewms.test']]);
        $admin = User::factory()->create()->assignRole('Administrator');
        $this->fakeGoogleUser('backups@ewms.test', refreshToken: null);

        $this->actingAs($admin)
            ->get('/settings/integrations/google-drive/callback')
            ->assertSessionHasErrors('drive');

        $this->assertNull(CompanySetting::current()->google_drive_refresh_token);
    }

    public function test_administrator_can_disconnect()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        CompanySetting::current()->update([
            'google_drive_connected_email' => 'backups@ewms.test',
            'google_drive_access_token' => 'a',
            'google_drive_refresh_token' => 'r',
            'google_drive_folder_id' => 'folder-1',
        ]);

        $this->actingAs($admin)->delete('/settings/integrations/google-drive')->assertRedirect();

        $settings = CompanySetting::current()->fresh();
        $this->assertNull($settings->google_drive_connected_email);
        $this->assertNull($settings->google_drive_refresh_token);
        $this->assertNull($settings->google_drive_folder_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'google_drive_disconnected']);
    }
}
