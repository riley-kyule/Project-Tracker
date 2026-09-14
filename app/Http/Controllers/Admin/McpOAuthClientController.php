<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpOAuthClient;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Self-service registration for the MCP OAuth connector flow (see
 * McpOAuthController) — each person registers their own client here, one row
 * per external AI they connect (their ChatGPT, their Claude), rather than
 * EWMS having a single fixed client shared by everyone.
 */
class McpOAuthClientController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('mcp.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            // Must be an absolute https URL and globally unique — it's the
            // redirect_uri the connecting AI itself generated for this one
            // connector instance, so two people (or the same person twice)
            // registering can never collide on it in practice.
            'redirect_uri' => ['required', 'string', 'max:500', 'url', 'starts_with:https://', Rule::unique('mcp_oauth_clients', 'redirect_uri')],
        ]);

        [$client, $clientSecret] = McpOAuthClient::issue($request->user(), $validated['name'], $validated['redirect_uri']);
        AuditLogger::log($client, 'mcp_oauth_client.created', [], ['name' => $client->name]);

        // The secret exists only in this one response — flashed, not stored,
        // so a page refresh (or anyone else who later opens this page) never sees it again.
        return back()->with('newOAuthClient', ['clientId' => $client->client_id, 'clientSecret' => $clientSecret]);
    }

    public function destroy(Request $request, McpOAuthClient $oauthClient): RedirectResponse
    {
        abort_unless($request->user()->can('mcp.manage'), 403);
        abort_unless($oauthClient->user_id === $request->user()->id, 403);

        // Disconnecting the connector also revokes whatever access token(s)
        // it issued — the same "revoke this app" behavior a real OAuth
        // provider gives you, not just a promise to stop minting new ones.
        $revokedTokens = $oauthClient->tokens()->count();
        $oauthClient->tokens()->delete();

        AuditLogger::log($oauthClient, 'mcp_oauth_client.revoked', ['name' => $oauthClient->name, 'tokens_revoked' => $revokedTokens], []);
        $oauthClient->delete();

        return back();
    }
}
