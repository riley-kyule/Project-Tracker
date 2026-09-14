<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per person's own connection to one external AI client
        // (their ChatGPT, their Claude) — self-service, mirroring how a
        // bearer token is issued today. redirect_uri is unique because it's
        // effectively that external client's own identity: ChatGPT (or
        // Claude) mints a fresh one per connector instance, so two people
        // each registering "ChatGPT" naturally get two different rows.
        Schema::create('mcp_oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('client_id', 40)->unique();
            // Same hashing reasoning as mcp_tokens.token_hash — shown once, at
            // registration, never recoverable from the database afterward.
            $table->string('client_secret_hash', 64);
            $table->string('redirect_uri')->unique();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_clients');
    }
};
