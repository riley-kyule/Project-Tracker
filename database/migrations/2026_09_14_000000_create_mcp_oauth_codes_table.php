<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_oauth_codes', function (Blueprint $table) {
            $table->id();
            // Hashed, same reasoning as McpToken — a database read alone can't
            // replay a still-valid code. Codes are single-use and short-lived
            // (5 minutes) regardless.
            $table->string('code_hash', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('redirect_uri');
            $table->string('code_challenge')->nullable();
            $table->string('code_challenge_method', 10)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_codes');
    }
};
