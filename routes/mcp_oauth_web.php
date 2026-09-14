<?php

use App\Http\Controllers\Mcp\McpOAuthController;
use App\Http\Controllers\Mcp\WellKnownController;
use Illuminate\Support\Facades\Route;

// Discovery documents (RFC 8414 / RFC 9728) — unauthenticated, and must sit
// at the site root, not under /api, since a client resolves them from the
// bare issuer URL before it has any credentials at all. ChatGPT's connector
// setup fetches oauth-authorization-server before it'll let you finish
// adding EWMS — without it (or without code_challenge_methods_supported
// advertising S256) it refuses with "must advertise PKCE support."
Route::get('.well-known/oauth-authorization-server', [WellKnownController::class, 'authorizationServer'])->name('mcp.oauth.metadata');
Route::get('.well-known/oauth-protected-resource', [WellKnownController::class, 'protectedResource'])->name('mcp.oauth.protected-resource');

// The interactive half of the MCP OAuth flow — needs a real EWMS session
// (so an unauthenticated visit bounces through the normal login first) and
// CSRF protection on the consent form post. The stateless token exchange
// lives in routes/mcp.php instead. See McpOAuthController.
Route::middleware(['auth'])->group(function () {
    Route::get('oauth/authorize', [McpOAuthController::class, 'authorize'])->name('mcp.oauth.authorize');
    Route::post('oauth/authorize', [McpOAuthController::class, 'approve'])->name('mcp.oauth.approve');
    Route::post('oauth/deny', [McpOAuthController::class, 'deny'])->name('mcp.oauth.deny');
});
