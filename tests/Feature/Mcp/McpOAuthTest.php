<?php

namespace Tests\Feature\Mcp;

use App\Models\McpOAuthClient;
use App\Models\McpOAuthCode;
use App\Models\McpToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT_URI = 'https://chatgpt.com/connector/oauth/abc123';

    /** @return array{0: User, 1: McpOAuthClient, 2: string} [owner, client row, plaintext secret] */
    private function registerClient(?User $owner = null, ?string $redirectUri = null): array
    {
        $owner ??= User::factory()->create()->assignRole('CEO');
        [$client, $secret] = McpOAuthClient::issue($owner, 'ChatGPT', $redirectUri ?? self::REDIRECT_URI);

        return [$owner, $client, $secret];
    }

    public function test_the_authorization_server_metadata_document_advertises_pkce_support(): void
    {
        $response = $this->getJson('/.well-known/oauth-authorization-server');

        $response->assertOk();
        $response->assertJsonPath('authorization_endpoint', url('/oauth/authorize'));
        $response->assertJsonPath('token_endpoint', url('/api/oauth/token'));
        $response->assertJsonPath('code_challenge_methods_supported', ['S256']);
        $response->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token']);
    }

    public function test_the_protected_resource_metadata_document_points_at_this_authorization_server(): void
    {
        $response = $this->getJson('/.well-known/oauth-protected-resource');

        $response->assertOk();
        $response->assertJsonPath('resource', url('/api/mcp'));
        $response->assertJsonPath('authorization_servers', [url('/')]);
    }

    public function test_authorize_requires_login(): void
    {
        [, $client] = $this->registerClient();

        $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz',
        ]))->assertRedirect('/login');
    }

    public function test_authorize_rejects_an_unregistered_client_id_or_mismatched_redirect_uri(): void
    {
        [, $client] = $this->registerClient();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => 'ewms-never-registered',
            'redirect_uri' => self::REDIRECT_URI,
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/error'));

        $this->actingAs($ceo)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => 'https://evil.example.com/callback',
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/error'));
    }

    public function test_authorize_requires_mcp_manage(): void
    {
        [, $client] = $this->registerClient();
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => self::REDIRECT_URI,
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/error'));
    }

    public function test_a_logged_in_permitted_user_sees_the_consent_screen_with_the_connector_name(): void
    {
        [, $client] = $this->registerClient();
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz',
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/authorize')
            ->where('params.client_id', $client->client_id)
            ->where('params.state', 'xyz')
            ->where('clientName', 'ChatGPT'));
    }

    public function test_the_exact_params_handed_to_the_consent_screen_are_enough_to_approve(): void
    {
        // Regression test: the consent page's "Allow" button submits exactly
        // the `params` prop the GET response gave it (useForm(params).post()),
        // nothing hand-picked. A field present in the query string but missing
        // from that prop (response_type was, once) passes every unit test that
        // manually rebuilds the POST body but 400s for real — this drives the
        // POST from the GET response's own props instead, so it can't happen again.
        [$ceo, $client] = $this->registerClient();

        // A plain (non-X-Inertia) visit — like the browser's actual first
        // load of the link ChatGPT redirects to — renders the full page with
        // props embedded in it, which assertInertia() can read back exactly
        // as the frontend would receive them via the Inertia page object.
        $params = null;
        $this->actingAs($ceo)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz',
            'code_challenge' => 'abc',
            'code_challenge_method' => 'S256',
        ]))->assertInertia(function ($page) use (&$params) {
            $page->component('mcp-oauth/authorize');
            $params = $page->toArray()['props']['params'];
        });
        $this->assertNotNull($params);

        $approve = $this->actingAs($ceo)->withHeader('X-Inertia', 'true')->post('/oauth/authorize', $params);

        $approve->assertStatus(409);
        $this->assertSame(1, McpOAuthCode::query()->count());
    }

    public function test_two_different_people_can_each_register_their_own_connector_and_authorize_independently(): void
    {
        $alice = User::factory()->create()->assignRole('CEO');
        $bob = User::factory()->create()->assignRole('Administrator');
        [$aliceClient] = McpOAuthClient::issue($alice, 'ChatGPT', 'https://chatgpt.com/connector/oauth/alice');
        [$bobClient] = McpOAuthClient::issue($bob, 'ChatGPT', 'https://chatgpt.com/connector/oauth/bob');

        $this->assertNotSame($aliceClient->client_id, $bobClient->client_id);

        $this->actingAs($alice)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => $aliceClient->client_id, 'redirect_uri' => $aliceClient->redirect_uri,
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/authorize'));

        $this->actingAs($bob)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => $bobClient->client_id, 'redirect_uri' => $bobClient->redirect_uri,
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/authorize'));

        // Alice's registered redirect_uri doesn't work with Bob's client_id, and vice versa.
        $this->actingAs($alice)->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => $bobClient->client_id, 'redirect_uri' => $aliceClient->redirect_uri,
        ]))->assertInertia(fn ($page) => $page->component('mcp-oauth/error'));
    }

    public function test_approving_issues_a_single_use_code_and_bounces_back_with_it(): void
    {
        [$ceo, $client] = $this->registerClient();

        // Inertia::location() only emits the 409 + X-Inertia-Location handoff
        // for an actual Inertia (XHR) visit — which is what the consent
        // page's useForm().post() sends in the browser — so simulate that.
        $response = $this->actingAs($ceo)->withHeader('X-Inertia', 'true')->post('/oauth/authorize', [
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz',
        ]);

        $response->assertStatus(409);
        $location = $response->headers->get('X-Inertia-Location');
        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $location);
        $this->assertStringContainsString('state=xyz', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);

        $this->assertSame(1, McpOAuthCode::query()->count());
        $this->assertSame($client->id, McpOAuthCode::query()->first()->mcp_oauth_client_id);
    }

    public function test_denying_bounces_back_with_an_error_and_no_code(): void
    {
        [$ceo, $client] = $this->registerClient();

        $response = $this->actingAs($ceo)->withHeader('X-Inertia', 'true')->post('/oauth/deny', [
            'client_id' => $client->client_id,
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz',
        ]);

        $response->assertStatus(409);
        $location = $response->headers->get('X-Inertia-Location');
        $this->assertStringContainsString('error=access_denied', $location);
        $this->assertSame(0, McpOAuthCode::query()->count());
    }

    public function test_the_token_endpoint_rejects_an_unregistered_client_or_wrong_secret(): void
    {
        [, $client] = $this->registerClient();

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'client_secret' => 'wrong',
            'code' => 'whatever',
            'redirect_uri' => self::REDIRECT_URI,
        ])->assertStatus(401)->assertJsonPath('error', 'invalid_client');

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'ewms-never-registered',
            'client_secret' => 'whatever',
            'code' => 'whatever',
            'redirect_uri' => self::REDIRECT_URI,
        ])->assertStatus(401)->assertJsonPath('error', 'invalid_client');
    }

    public function test_the_full_authorization_code_exchange_issues_a_working_mcp_token(): void
    {
        [$ceo, $client, $secret] = $this->registerClient();

        $approve = $this->actingAs($ceo)->withHeader('X-Inertia', 'true')->post('/oauth/authorize', [
            'response_type' => 'code',
            'client_id' => $client->client_id,
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz',
        ]);
        parse_str(parse_url($approve->headers->get('X-Inertia-Location'), PHP_URL_QUERY), $query);
        $code = $query['code'];

        $token = $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'code' => $code,
            'redirect_uri' => self::REDIRECT_URI,
        ]);

        $token->assertOk();
        $token->assertJsonPath('token_type', 'Bearer');
        $accessToken = $token->json('access_token');
        $this->assertNotEmpty($accessToken);
        $this->assertSame($client->id, McpToken::resolve($accessToken)->mcp_oauth_client_id);

        // The code is now spent — replaying it must fail.
        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'code' => $code,
            'redirect_uri' => self::REDIRECT_URI,
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        // The issued access_token is a real, working McpToken.
        $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertOk();
    }

    public function test_a_code_cannot_be_redeemed_by_a_different_client_than_it_was_issued_to(): void
    {
        [$ceo, $clientA] = $this->registerClient();
        [, $clientB, $secretB] = $this->registerClient(User::factory()->create()->assignRole('Administrator'), 'https://claude.ai/api/mcp/other-client');
        // clientB has its own redirect_uri, but that's irrelevant here — the
        // point is clientA's code, redeemed with clientB's credentials.
        [, $plaintext] = McpOAuthCode::issue($ceo, $clientA, self::REDIRECT_URI, null, null);

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientB->client_id,
            'client_secret' => $secretB,
            'code' => $plaintext,
            'redirect_uri' => self::REDIRECT_URI,
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_a_redirect_uri_mismatch_at_the_token_endpoint_is_rejected(): void
    {
        [$ceo, $client, $secret] = $this->registerClient();
        [, $plaintext] = McpOAuthCode::issue($ceo, $client, self::REDIRECT_URI, null, null);

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'code' => $plaintext,
            'redirect_uri' => self::REDIRECT_URI.'-DIFFERENT',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_pkce_verification_is_enforced_when_a_code_challenge_was_used(): void
    {
        [$ceo, $client, $secret] = $this->registerClient();
        $verifier = bin2hex(random_bytes(32));
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        [, $plaintext] = McpOAuthCode::issue($ceo, $client, self::REDIRECT_URI, $challenge, 'S256');

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'code' => $plaintext,
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => 'wrong-verifier',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'code' => $plaintext,
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => $verifier,
        ])->assertOk();
    }

    public function test_an_expired_code_is_rejected(): void
    {
        [$ceo, $client, $secret] = $this->registerClient();
        [$record, $plaintext] = McpOAuthCode::issue($ceo, $client, self::REDIRECT_URI, null, null);
        $record->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'code' => $plaintext,
            'redirect_uri' => self::REDIRECT_URI,
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_refresh_token_grant_reconfirms_a_still_valid_token_issued_to_the_same_client(): void
    {
        [$ceo, $client, $secret] = $this->registerClient();
        [, $plaintext] = McpToken::issue($ceo, 'ChatGPT (OAuth)', $client->id);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'refresh_token' => $plaintext,
        ]);

        $response->assertOk();
        $this->assertSame($plaintext, $response->json('access_token'));
    }

    public function test_refresh_token_grant_rejects_a_token_issued_to_a_different_client(): void
    {
        [$ceo, $clientA] = $this->registerClient();
        [, $clientB, $secretB] = $this->registerClient(User::factory()->create()->assignRole('Administrator'), 'https://claude.ai/api/mcp/other-client');
        [, $plaintext] = McpToken::issue($ceo, 'ChatGPT (OAuth)', $clientA->id);

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientB->client_id,
            'client_secret' => $secretB,
            'refresh_token' => $plaintext,
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_refresh_token_grant_rejects_a_revoked_token(): void
    {
        [, $client, $secret] = $this->registerClient();

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->client_id,
            'client_secret' => $secret,
            'refresh_token' => 'not-a-real-token',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }
}
