<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Models\McpOAuthCode;
use App\Models\McpToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A minimal OAuth 2.0 authorization-code flow in front of the existing
 * McpToken bearer-token system — see config/mcp_oauth.php for why this
 * exists. One fixed client (config-defined), not a general-purpose
 * multi-tenant authorization server: authorize()/approve() are the
 * interactive, session-authenticated half (a normal EWMS-logged-in user
 * clicking Allow); token() is the stateless half the connecting client's
 * own backend calls directly, authenticated by client_id/secret (and PKCE,
 * if the request included it) rather than a session.
 */
class McpOAuthController extends Controller
{
    public function authorize(Request $request): Response|SymfonyResponse
    {
        $error = $this->validateAuthorizeRequest($request);
        if ($error) {
            return Inertia::render('mcp-oauth/error', ['message' => $error]);
        }

        if (! $request->user()->can('mcp.manage')) {
            return Inertia::render('mcp-oauth/error', [
                'message' => "Your account doesn't have permission to connect an AI assistant to EWMS. Ask whoever holds the mcp.manage permission (CEO/Administrator by default).",
            ]);
        }

        return Inertia::render('mcp-oauth/authorize', [
            'params' => $request->only(['client_id', 'redirect_uri', 'state', 'scope', 'code_challenge', 'code_challenge_method']),
        ]);
    }

    public function approve(Request $request): Response|SymfonyResponse
    {
        $error = $this->validateAuthorizeRequest($request);
        abort_if($error, 400, $error);
        abort_unless($request->user()->can('mcp.manage'), 403);

        [, $plaintext] = McpOAuthCode::issue(
            $request->user(),
            $request->string('redirect_uri')->toString(),
            $request->input('code_challenge'),
            $request->input('code_challenge_method'),
        );

        $redirect = $request->string('redirect_uri')->toString()
            .(str_contains($request->string('redirect_uri'), '?') ? '&' : '?')
            .'code='.urlencode($plaintext)
            .'&state='.urlencode((string) $request->input('state', ''));

        // Inertia::location() forces a real browser navigation (409 +
        // X-Inertia-Location) rather than following the redirect as another
        // XHR — required here since the destination is off-site (chatgpt.com).
        return Inertia::location($redirect);
    }

    public function deny(Request $request): Response|SymfonyResponse
    {
        // Same exact-match rule as authorize()/approve() — a forged POST here
        // with an arbitrary redirect_uri is exactly the open redirect that
        // check exists to close, denial or not.
        abort_unless($request->string('redirect_uri')->toString() === config('mcp_oauth.redirect_uri'), 400);

        $redirect = $request->string('redirect_uri')->toString()
            .(str_contains($request->string('redirect_uri'), '?') ? '&' : '?')
            .'error=access_denied&state='.urlencode((string) $request->input('state', ''));

        return Inertia::location($redirect);
    }

    /** The client's backend calls this directly — no session, authenticated by client_id/secret and (if the authorize request used PKCE) code_verifier. */
    public function token(Request $request): JsonResponse
    {
        $clientId = config('mcp_oauth.client_id');
        $clientSecret = config('mcp_oauth.client_secret');

        if (! $clientId || ! $clientSecret) {
            return response()->json(['error' => 'server_error', 'error_description' => 'MCP OAuth is not configured on this server.'], 500);
        }

        if ($request->input('client_id') !== $clientId || $request->input('client_secret') !== $clientSecret) {
            return response()->json(['error' => 'invalid_client'], 401);
        }

        $grantType = $request->string('grant_type')->toString();

        return match ($grantType) {
            'authorization_code' => $this->exchangeCode($request),
            'refresh_token' => $this->exchangeRefreshToken($request),
            default => response()->json(['error' => 'unsupported_grant_type'], 400),
        };
    }

    private function exchangeCode(Request $request): JsonResponse
    {
        $plaintext = $request->string('code')->toString();
        $code = $plaintext !== '' ? McpOAuthCode::resolveValid($plaintext) : null;

        if ($code === null) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'That code is invalid, expired, or already used.'], 400);
        }

        if ($code->redirect_uri !== $request->string('redirect_uri')->toString()) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'redirect_uri does not match the one used to request this code.'], 400);
        }

        if ($code->code_challenge !== null) {
            $verifier = $request->string('code_verifier')->toString();
            $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            if ($code->code_challenge_method !== 'S256' || ! hash_equals($code->code_challenge, $expected)) {
                return response()->json(['error' => 'invalid_grant', 'error_description' => 'PKCE verification failed.'], 400);
            }
        }

        // Redeem atomically — a code can only ever produce one token, even
        // under a racing double-submit of the same exchange request.
        $redeemed = McpOAuthCode::query()->whereKey($code->id)->whereNull('used_at')->update(['used_at' => now()]);
        if ($redeemed === 0) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'That code was already used.'], 400);
        }

        [, $accessToken] = McpToken::issue($code->user, 'ChatGPT (OAuth) '.now()->toDateString());

        return $this->tokenResponse($accessToken);
    }

    /** McpToken never expires, so "refreshing" is a no-op that just re-confirms the same token still exists and is still valid — no new token is minted. */
    private function exchangeRefreshToken(Request $request): JsonResponse
    {
        $token = McpToken::resolve($request->string('refresh_token')->toString());

        if ($token === null) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'That refresh token has been revoked.'], 400);
        }

        return $this->tokenResponse($request->string('refresh_token')->toString());
    }

    private function tokenResponse(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
            'refresh_token' => $token,
            'scope' => 'mcp',
        ]);
    }

    private function validateAuthorizeRequest(Request $request): ?string
    {
        if ($request->string('response_type')->toString() !== 'code') {
            return 'Only the "code" response type is supported.';
        }

        if ($request->input('client_id') !== config('mcp_oauth.client_id')) {
            return 'Unrecognized client_id.';
        }

        $redirectUri = $request->string('redirect_uri')->toString();
        if ($redirectUri === '' || $redirectUri !== config('mcp_oauth.redirect_uri')) {
            // Deliberately never redirects back to an unrecognized redirect_uri — that's
            // exactly the open-redirect hole OAuth's exact-match requirement exists to close.
            return "redirect_uri doesn't match the one configured on this server.";
        }

        return null;
    }
}
