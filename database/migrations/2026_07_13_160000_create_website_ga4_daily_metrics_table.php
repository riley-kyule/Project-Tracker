<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_ga4_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('engaged_sessions')->default(0);
            $table->jsonb('key_events')->nullable();
            $table->timestamps();

            $table->unique(['website_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_ga4_daily_metrics');
    }
};
