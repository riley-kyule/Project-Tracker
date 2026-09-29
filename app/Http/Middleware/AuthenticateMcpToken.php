<?php

namespace App\Http\Middleware;

use App\Models\McpToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // An inactive or suspended account can't sign in to EWMS, so an AI
        // assistant it connected earlier mustn't keep acting for it either.
        // Rejected rather than deleted, so reactivating the account restores it.
        if (! $token->user->isActive()) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'error' => ['code' => -32001, 'message' => "Unauthorized — this token's EWMS account is no longer active."],
            ], 401);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->setUserResolver(fn () => $token->user);
        // Also the guard's user, so everything downstream that asks auth() —
        // AuditLogger's actor above all — attributes the work to the token's owner.
        Auth::setUser($token->user);
        $request->attributes->set('mcp_token', $token);

        return $next($request);
    }
}
