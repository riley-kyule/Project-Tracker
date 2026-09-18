<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Popcash's real API response likely has more fields than the three EWMS
 * currently has typed columns for (money_spent/cpm/impressions) — `raw`
 * keeps the untouched row so nothing the API returns is discarded, even
 * before those extra fields are confirmed/promoted to their own columns.
 * See PopcashApiClient::dailyStats().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_popcash_daily_spend', function (Blueprint $table) {
            $table->json('raw')->nullable()->after('impressions');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_popcash_daily_spend', function (Blueprint $table) {
            $table->dropColumn('raw');
        });
    }
};
