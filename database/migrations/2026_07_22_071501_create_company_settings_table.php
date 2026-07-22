<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton row (id = 1 by convention, enforced in CompanySetting::current())
        // rather than a generic key-value store — there are exactly two fields here
        // and no other setting exists yet to justify that abstraction.
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->time('ceo_summary_time')->nullable();
            $table->date('ceo_summary_last_sent_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
