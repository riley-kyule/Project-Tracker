<?php

use App\Models\CompanySetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            // Customer Service Board Spec v1.0 §8 "Response time": the
            // applicable service standard, per channel, in minutes. No
            // figures were supplied by the business at build time, so these
            // are reasonable defaults (set in the migration's data step
            // below) that an administrator can change from Settings without
            // a deployment — never hardcoded in application code.
            $table->json('cs_response_time_standards')->nullable();
        });

        CompanySetting::current()->update([
            'cs_response_time_standards' => ['chat' => 5, 'call' => 15, 'email' => 60, 'other' => 60],
        ]);
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('cs_response_time_standards');
        });
    }
};
