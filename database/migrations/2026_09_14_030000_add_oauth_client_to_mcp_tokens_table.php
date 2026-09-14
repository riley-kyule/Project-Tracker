<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null for a token created directly at /admin/mcp (a plain bearer
        // token); set when a token was minted by an OAuth code exchange —
        // lets the OAuth token endpoint scope the refresh_token grant to the
        // client that received it, and lets revoking a connector (see
        // McpOAuthClientController::destroy) also revoke the tokens it issued.
        Schema::table('mcp_tokens', function (Blueprint $table) {
            $table->foreignId('mcp_oauth_client_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mcp_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mcp_oauth_client_id');
        });
    }
};
