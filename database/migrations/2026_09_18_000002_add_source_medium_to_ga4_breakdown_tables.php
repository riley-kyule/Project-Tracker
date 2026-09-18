<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets GA4 users/key-events/locations be scoped to a specific traffic
 * source+medium (e.g. source=popcash, medium=cpm) instead of only whole-site
 * totals — needed to show Popcash-attributed GA4 performance rather than
 * generic site traffic next to Popcash ad spend. See AnalyticsSyncService
 * and TrafficDashboardQuery's *BySourceMedium() methods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_ga4_traffic_sources', function (Blueprint $table) {
            $table->unsignedBigInteger('sessions')->default(0)->after('users');
            $table->unsignedBigInteger('engaged_sessions')->default(0)->after('sessions');
        });

        Schema::table('analytics_ga4_key_events', function (Blueprint $table) {
            $table->string('source')->nullable()->after('category');
            $table->string('medium')->nullable()->after('source');
        });

        Schema::table('analytics_ga4_geo', function (Blueprint $table) {
            $table->string('source')->nullable()->after('city');
            $table->string('medium')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_ga4_geo', function (Blueprint $table) {
            $table->dropColumn(['source', 'medium']);
        });

        Schema::table('analytics_ga4_key_events', function (Blueprint $table) {
            $table->dropColumn(['source', 'medium']);
        });

        Schema::table('analytics_ga4_traffic_sources', function (Blueprint $table) {
            $table->dropColumn(['sessions', 'engaged_sessions']);
        });
    }
};
