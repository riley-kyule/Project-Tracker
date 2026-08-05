<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Defaults to off: daily summaries otherwise fire every day
            // regardless of it being a working day, which is the exact
            // complaint this column exists to fix. A department opts back
            // in explicitly if it actually wants Sunday reports.
            $table->boolean('send_sunday_reports')->default(false)->after('weekly_summary_time');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('send_sunday_reports');
        });
    }
};
