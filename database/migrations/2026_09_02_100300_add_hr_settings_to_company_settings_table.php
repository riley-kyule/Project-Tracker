<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR-module mail identity. HR notifications (leave, contract renewals,
 * payslips) go out under their own sender name but from the same configured
 * "from" address as the rest of the platform. Null falls back to the global
 * mail_from_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('hr_from_name')->nullable()->after('mail_from_name');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('hr_from_name');
        });
    }
};
