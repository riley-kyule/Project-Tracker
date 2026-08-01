<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Null means "don't send weekly personal summaries to this
            // department's employees" — mirrors daily_summary_time, but the
            // day is fixed at Friday (see SendWeeklySummaries) so only the
            // time is configurable, same as the daily setting.
            $table->time('weekly_summary_time')->nullable()->after('daily_summary_last_sent_on');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('weekly_summary_time');
        });
    }
};
