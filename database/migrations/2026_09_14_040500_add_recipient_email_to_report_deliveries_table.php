<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The SEO daily-card report (SEO Board Spec v1.1 §4.2.1) can go to a
        // configured recipient who has no EWMS user account — recipient_user_id
        // alone can't represent that. Existing rows are unaffected: every
        // caller today always sets recipient_user_id.
        Schema::table('report_deliveries', function (Blueprint $table) {
            $table->foreignId('recipient_user_id')->nullable()->change();
            $table->string('recipient_email')->nullable()->after('recipient_user_id');
            $table->string('recipient_name')->nullable()->after('recipient_email');
        });
    }

    public function down(): void
    {
        Schema::table('report_deliveries', function (Blueprint $table) {
            $table->dropColumn(['recipient_email', 'recipient_name']);
            $table->foreignId('recipient_user_id')->nullable(false)->change();
        });
    }
};
