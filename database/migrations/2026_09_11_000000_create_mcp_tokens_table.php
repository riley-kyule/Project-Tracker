<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Only the SHA-256 hash is stored — same reasoning as Laravel's own
            // personal access tokens — so a database read alone can't recover a
            // live token; the plaintext is shown to the issuer exactly once, at
            // creation, and never again.
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tokens');
    }
};
