<?php

namespace App\Http\Middleware;

use App\Models\McpToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the MCP endpoint — deliberately not the `web` guard
 * (no session/CSRF; an external AI client is never going to carry a cookie)
 * and not Sanctum (this app doesn't otherwise use it). setUserResolver
 * scopes the authenticated user to this request only, so it never touches
 * the session-backed `web` guard other code in the app relies on.
 */
class AuthenticateMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $request->bearerToken();
        $token = $plaintext !== null ? McpToken::resolve($plaintext) : null;

        if ($token === null || $token->user === null) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'error' => ['code' => -32001, 'message' => 'Unauthorized — missing or invalid bearer token.'],
            ], 401);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
