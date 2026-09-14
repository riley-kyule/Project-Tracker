<?php

use App\Http\Controllers\Mcp\McpOAuthController;
use App\Http\Controllers\Mcp\McpServerController;
use Illuminate\Support\Facades\Route;

// Mounted under /api (Laravel's default "api" prefix for the `api:` routing
// group) with the framework's default `api` middleware group — stateless,
// throttled, no CSRF — plus mcp.auth for the bearer token itself. See
// AuthenticateMcpToken and docs/MCP_CONNECTOR.md.
Route::post('mcp', [McpServerController::class, 'handle'])->middleware('mcp.auth')->name('mcp.handle');

// The OAuth token endpoint is called directly by the connecting client's own
// backend (e.g. ChatGPT's), never from a browser — no session, no CSRF, same
// stateless group as /api/mcp itself. The interactive half (/oauth/authorize)
// lives in routes/web.php since it needs a logged-in EWMS session.
Route::post('oauth/token', [McpOAuthController::class, 'token'])->name('mcp.oauth.token');
