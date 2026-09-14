<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpOAuthClient;
use App\Models\McpToken;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class McpTokenController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('mcp.manage'), 403);

        return Inertia::render('admin/mcp/index', [
            'tokens' => $request->user()->mcpTokens()->orderByDesc('id')->get()->map(fn (McpToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ]),
            'endpoint' => url('/api/mcp'),
            // These two URLs never change per person — only client_id/secret/
            // redirect_uri (below) are unique to each registered connector.
            'oauthUrls' => [
                'authorize' => url('/oauth/authorize'),
                'token' => url('/api/oauth/token'),
                'scope' => 'mcp',
            ],
            'oauthClients' => $request->user()->mcpOAuthClients()->orderByDesc('id')->get()->map(fn (McpOAuthClient $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'client_id' => $client->client_id,
                'redirect_uri' => $client->redirect_uri,
                'last_used_at' => $client->last_used_at,
                'created_at' => $client->created_at,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('mcp.manage'), 403);

        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        [$token, $plaintext] = McpToken::issue($request->user(), $validated['name']);
        AuditLogger::log($token, 'mcp_token.created', [], ['name' => $token->name]);

        // The plaintext exists only in this one response — flashed, not stored,
        // so a page refresh (or anyone else who later opens this page) never sees it again.
        return back()->with('newToken', $plaintext);
    }

    public function destroy(Request $request, McpToken $token): RedirectResponse
    {
        abort_unless($request->user()->can('mcp.manage'), 403);
        abort_unless($token->user_id === $request->user()->id, 403);

        AuditLogger::log($token, 'mcp_token.revoked', ['name' => $token->name], []);
        $token->delete();

        return back();
    }
}
