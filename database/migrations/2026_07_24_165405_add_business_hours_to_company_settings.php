<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->time('business_hours_start')->nullable();
            $table->time('business_hours_end')->nullable();
            // JSON list of ISO weekday numbers (1 = Monday .. 7 = Sunday) the office is open.
            $table->json('business_hours_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['business_hours_start', 'business_hours_end', 'business_hours_days']);
        });
    }
};
