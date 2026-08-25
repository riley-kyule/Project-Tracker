<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wp_user_id'); // the remote WP user's numeric ID, not an EWMS user
            $table->string('username');
            $table->string('email')->nullable();
            $table->string('display_name')->nullable();
            $table->jsonb('roles'); // WP role slugs, e.g. ["administrator","editor"]
            $table->timestamp('wp_registered_at')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
            $table->unique(['website_id', 'wp_user_id']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_users');
    }
};
