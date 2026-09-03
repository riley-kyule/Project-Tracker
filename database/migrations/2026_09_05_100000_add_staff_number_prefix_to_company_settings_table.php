<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The prefix new employee staff numbers are suggested with, e.g. "EXO" → EXO-030.
 * Null means "infer it from the existing records".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('staff_number_prefix', 20)->nullable()->after('payroll_requires_second_approval');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('staff_number_prefix');
        });
    }
};
