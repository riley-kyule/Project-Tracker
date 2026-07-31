<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Company-wide business day / report-schedule timezone. Distinct from
            // users.timezone, which is a per-user display preference.
            $table->string('timezone')->default('Africa/Nairobi');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
