<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_ga4_daily_metrics', function (Blueprint $table) {
            $table->id(); $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->date('data_date');
            $table->unsignedBigInteger('users')->default(0); $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('new_users')->default(0); $table->unsignedBigInteger('engaged_sessions')->default(0);
            $table->decimal('engagement_seconds', 20, 4)->default(0); $table->timestamps();
            $table->unique(['website_id', 'data_date']); $table->index('data_date');
        });
        foreach (['traffic_sources', 'devices', 'pages', 'geo', 'key_events'] as $name) {
            Schema::create("analytics_ga4_{$name}", function (Blueprint $table) use ($name) {
                $table->id(); $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->date('data_date');
                if ($name === 'traffic_sources') { $table->string('source'); $table->string('medium'); }
                elseif ($name === 'devices') $table->string('device_category');
                elseif ($name === 'pages') { $table->text('page_location'); $table->unsignedBigInteger('page_views')->default(0); }
                elseif ($name === 'geo') { $table->string('user_country')->nullable(); $table->string('city')->nullable(); }
                else { $table->string('event_name'); $table->string('display_name'); $table->string('category')->default('key_event'); $table->unsignedBigInteger('event_count')->default(0); }
                $table->unsignedBigInteger('users')->default(0); $table->timestamps();
                $table->index(['website_id', 'data_date']); $table->index('data_date');
            });
        }
        foreach (['site', 'queries', 'pages', 'countries', 'devices'] as $name) {
            Schema::create("analytics_gsc_daily_{$name}", function (Blueprint $table) use ($name) {
                $table->id(); $table->foreignId('website_id')->constrained()->cascadeOnDelete(); $table->date('data_date'); $table->string('search_type', 16)->default('WEB');
                if ($name === 'queries') $table->text('query'); if ($name === 'pages') $table->text('url');
                if ($name === 'countries') $table->string('country', 8); if ($name === 'devices') $table->string('device', 32);
                $table->unsignedBigInteger('clicks')->default(0); $table->unsignedBigInteger('impressions')->default(0);
                $table->decimal('position_sum', 24, 6)->default(0); $table->timestamps();
                $table->index(['website_id', 'data_date', 'search_type']); $table->index('data_date');
            });
        }
        Schema::create('analytics_sync_runs', function (Blueprint $table) {
            $table->id(); $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete(); $table->string('source', 16); $table->date('data_date');
            $table->string('status', 16)->index(); $table->unsignedInteger('rows_written')->default(0); $table->text('error')->nullable();
            $table->timestampTz('started_at'); $table->timestampTz('finished_at')->nullable(); $table->timestamps();
            $table->unique(['website_id', 'source', 'data_date']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('analytics_sync_runs');
        foreach (['devices','countries','pages','queries','site'] as $name) Schema::dropIfExists("analytics_gsc_daily_{$name}");
        foreach (['key_events','geo','pages','devices','traffic_sources'] as $name) Schema::dropIfExists("analytics_ga4_{$name}");
        Schema::dropIfExists('analytics_ga4_daily_metrics');
    }
};
