<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * OAuth/MCP discovery documents — unauthenticated, publicly fetched by the
 * connecting client's backend *before* it ever shows the user (or accepts)
 * an authorize/token URL, so it can validate the server supports what it
 * needs (PKCE, in ChatGPT's case) without a person manually confirming
 * anything. See McpOAuthController for the endpoints these describe.
 */
class WellKnownController extends Controller
{
    /** RFC 8414 — OAuth 2.0 Authorization Server Metadata. */
    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => url('/oauth/authorize'),
            'token_endpoint' => url('/api/oauth/token'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post'],
            'scopes_supported' => ['mcp'],
        ]);
    }

    /** RFC 9728 — OAuth 2.0 Protected Resource Metadata, for the MCP endpoint itself. Lets a client that starts from the resource URL (rather than being handed the authorize URL directly) discover which authorization server to use. */
    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource' => url('/api/mcp'),
            'authorization_servers' => [url('/')],
            'scopes_supported' => ['mcp'],
            'bearer_methods_supported' => ['header'],
        ]);
    }
}
