<?php

namespace Tests\Feature\Mcp;

use App\Models\McpOAuthCode;
use App\Models\McpToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private function configureClient(): void
    {
        Config::set('mcp_oauth.client_id', 'test-client');
        Config::set('mcp_oauth.client_secret', 'test-secret');
        Config::set('mcp_oauth.redirect_uri', 'https://chatgpt.com/connector/oauth/abc123');
    }

    public function test_authorize_requires_login(): void
    {
        $this->configureClient();

        $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => 'test-client',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
            'state' => 'xyz',
        ]))->assertRedirect('/login');
    }

    public function test_authorize_rejects_a_client_id_or_redirect_uri_that_does_not_match(): void
    {
        $this->configureClient();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => 'wrong-client',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/error'));

        $this->actingAs($ceo)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => 'test-client',
            'redirect_uri' => 'https://evil.example.com/callback',
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/error'));
    }

    public function test_authorize_requires_mcp_manage(): void
    {
        $this->configureClient();
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => 'test-client',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/error'));
    }

    public function test_a_logged_in_permitted_user_sees_the_consent_screen(): void
    {
        $this->configureClient();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => 'test-client',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
            'state' => 'xyz',
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/authorize')
            ->where('params.client_id', 'test-client')
            ->where('params.state', 'xyz'));
    }

    public function test_approving_issues_a_single_use_code_and_bounces_back_with_it(): void
    {
        $this->configureClient();
        $ceo = User::factory()->create()->assignRole('CEO');

        // Inertia::location() only emits the 409 + X-Inertia-Location handoff
        // for an actual Inertia (XHR) visit — which is what the consent
        // page's useForm().post() sends in the browser — so simulate that.
        $response = $this->actingAs($ceo)->withHeader('X-Inertia', 'true')->post('/oauth/authorize', [
            'response_type' => 'code',
            'client_id' => 'test-client',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
            'state' => 'xyz',
        ]);

        $response->assertStatus(409);
        $location = $response->headers->get('X-Inertia-Location');
        $this->assertStringStartsWith('https://chatgpt.com/connector/oauth/abc123?', $location);
        $this->assertStringContainsString('state=xyz', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);

        $this->assertSame(1, McpOAuthCode::query()->count());
    }

    public function test_denying_bounces_back_with_an_error_and_no_code(): void
    {
        $this->configureClient();
        $ceo = User::factory()->create()->assignRole('CEO');

        $response = $this->actingAs($ceo)->withHeader('X-Inertia', 'true')->post('/oauth/deny', [
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
            'state' => 'xyz',
        ]);

        $response->assertStatus(409);
        $location = $response->headers->get('X-Inertia-Location');
        $this->assertStringContainsString('error=access_denied', $location);
        $this->assertSame(0, McpOAuthCode::query()->count());
    }

    public function test_the_token_endpoint_rejects_a_wrong_client_secret(): void
    {
        $this->configureClient();

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'test-client',
            'client_secret' => 'wrong',
            'code' => 'whatever',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
        ])->assertStatus(401)->assertJsonPath('error', 'invalid_client');
    }

    public function test_the_full_authorization_code_exchange_issues_a_working_mcp_token(): void
    {
        $this->configureClient();
        $ceo = User::factory()->create()->assignRole('CEO');

        $approve = $this->actingAs($ceo)->withHeader('X-Inertia', 'true')->post('/oauth/authorize', [
            'response_type' => 'code',
            'client_id' => 'test-client',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
            'state' => 'xyz',
        ]);
        parse_str(parse_url($approve->headers->get('X-Inertia-Location'), PHP_URL_QUERY), $query);
        $code = $query['code'];

        $token = $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'code' => $code,
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
        ]);

        $token->assertOk();
        $token->assertJsonPath('token_type', 'Bearer');
        $accessToken = $token->json('access_token');
        $this->assertNotEmpty($accessToken);

        // The code is now spent — replaying it must fail.
        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'code' => $code,
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        // The issued access_token is a real, working McpToken.
        $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertOk();
    }

    public function test_a_redirect_uri_mismatch_at_the_token_endpoint_is_rejected(): void
    {
        $this->configureClient();
        $ceo = User::factory()->create()->assignRole('CEO');
        [, $plaintext] = McpOAuthCode::issue($ceo, 'https://chatgpt.com/connector/oauth/abc123', null, null);

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'code' => $plaintext,
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/DIFFERENT',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_pkce_verification_is_enforced_when_a_code_challenge_was_used(): void
    {
        $this->configureClient();
        $ceo = User::factory()->create()->assignRole('CEO');
        $verifier = bin2hex(random_bytes(32));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        [, $plaintext] = McpOAuthCode::issue($ceo, 'https://chatgpt.com/connector/oauth/abc123', $challenge, 'S256');

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'code' => $plaintext,
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
            'code_verifier' => 'wrong-verifier',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'code' => $plaintext,
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
            'code_verifier' => $verifier,
        ])->assertOk();
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $this->configureClient();
        $ceo = User::factory()->create()->assignRole('CEO');
        [$record, $plaintext] = McpOAuthCode::issue($ceo, 'https://chatgpt.com/connector/oauth/abc123', null, null);
        $record->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'code' => $plaintext,
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc123',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_refresh_token_grant_reconfirms_a_still_valid_token(): void
    {
        $this->configureClient();
        $ceo = User::factory()->create()->assignRole('CEO');
        [, $plaintext] = McpToken::issue($ceo, 'ChatGPT (OAuth)');

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'refresh_token' => $plaintext,
        ]);

        $response->assertOk();
        $this->assertSame($plaintext, $response->json('access_token'));
    }

    public function test_refresh_token_grant_rejects_a_revoked_token(): void
    {
        $this->configureClient();

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'refresh_token' => 'not-a-real-token',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }
}
