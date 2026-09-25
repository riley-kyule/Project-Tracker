<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Customer Service Board Spec v1.0 §14: scores display during a
            // 4-week calibration window but must not alone drive
            // disciplinary/remuneration decisions. Labeling only — set on
            // go-live, read by the dashboard banner. Mirrors
            // seo_calibration_ends_at.
            $table->date('cs_calibration_ends_at')->nullable();
            // §4 "Currency" — the company reporting currency cs_sales_records
            // amounts are converted into via cs_exchange_rates.
            $table->string('cs_reporting_currency', 3)->default('KES');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['cs_calibration_ends_at', 'cs_reporting_currency']);
        });
    }
};
