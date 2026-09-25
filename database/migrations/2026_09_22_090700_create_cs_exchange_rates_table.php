<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Service Board Requirements Specification v1.0 §4
        // "Currency": revenue is stored in transaction currency and the
        // company reporting currency using an approved exchange-rate source
        // and date. Per the implementation plan's confirmed dependency,
        // there is no external rate feed today, so this is a manually
        // maintained daily rate table (HOD/finance-managed) rather than a
        // live integration.
        Schema::create('cs_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date');
            $table->string('currency', 3);
            $table->decimal('rate_to_reporting_currency', 12, 6);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rate_date', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_exchange_rates');
    }
};
