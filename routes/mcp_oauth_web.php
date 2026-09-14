<?php

use App\Http\Controllers\Mcp\McpOAuthController;
use Illuminate\Support\Facades\Route;

// The interactive half of the MCP OAuth flow — needs a real EWMS session
// (so an unauthenticated visit bounces through the normal login first) and
// CSRF protection on the consent form post. The stateless token exchange
// lives in routes/mcp.php instead. See McpOAuthController.
Route::middleware(['auth'])->group(function () {
    Route::get('oauth/authorize', [McpOAuthController::class, 'authorize'])->name('mcp.oauth.authorize');
    Route::post('oauth/authorize', [McpOAuthController::class, 'approve'])->name('mcp.oauth.approve');
    Route::post('oauth/deny', [McpOAuthController::class, 'deny'])->name('mcp.oauth.deny');
});
