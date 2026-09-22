<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // SEO Board Spec v1.1 §13: scores display during a 4-week calibration
            // window but must not alone drive disciplinary/remuneration decisions.
            // Labeling only — set on go-live, read by the dashboard banner.
            $table->date('seo_calibration_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('seo_calibration_ends_at');
        });
    }
};
