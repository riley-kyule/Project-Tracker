<?php

use App\Http\Controllers\Mcp\McpServerController;
use Illuminate\Support\Facades\Route;

// Mounted under /api (Laravel's default "api" prefix for the `api:` routing
// group) with the framework's default `api` middleware group — stateless,
// throttled, no CSRF — plus mcp.auth for the bearer token itself. See
// AuthenticateMcpToken and docs/MCP_CONNECTOR.md.
Route::post('mcp', [McpServerController::class, 'handle'])->middleware('mcp.auth')->name('mcp.handle');
