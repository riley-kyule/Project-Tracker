<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_deliveries', function (Blueprint $table) {
            // Set once a failed delivery has triggered an administrator alert,
            // so a delivery that keeps failing across retries alerts once, not
            // on every retry attempt.
            $table->timestamp('admin_alerted_at')->nullable()->after('retry_count');
        });
    }

    public function down(): void
    {
        Schema::table('report_deliveries', function (Blueprint $table) {
            $table->dropColumn('admin_alerted_at');
        });
    }
};
