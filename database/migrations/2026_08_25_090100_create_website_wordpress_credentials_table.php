<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_wordpress_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('wp_username');
            $table->text('wp_app_password');
            $table->string('status')->default('unverified'); // unverified|ok|error
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique('website_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_wordpress_credentials');
    }
};
