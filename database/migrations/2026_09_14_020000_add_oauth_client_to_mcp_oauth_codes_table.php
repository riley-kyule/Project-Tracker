<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Binds every code to the specific registered client it was issued
        // for (see McpOAuthClient) — the token endpoint checks this in
        // addition to the redirect_uri match, so a code can only ever be
        // redeemed by the same client_id/secret pair that requested it.
        // Nullable at the schema level only because the column is being
        // added to a table that already exists (with zero rows — this
        // feature had not yet been used); McpOAuthCode::issue() always
        // requires a client, so in practice it's never actually null.
        Schema::table('mcp_oauth_codes', function (Blueprint $table) {
            $table->foreignId('mcp_oauth_client_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mcp_oauth_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mcp_oauth_client_id');
        });
    }
};
