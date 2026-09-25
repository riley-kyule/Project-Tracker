<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Service Board Requirements Specification v1.0 §8 "Response
        // time" / "Resolution": one row per customer enquiry, from receipt
        // through first response to resolution. Backs the weekly
        // "Customer-service quality" item's automatic achievement
        // calculation (CsScoringService::refreshServiceQualityAchievement())
        // the same way cs_sales_records backs the two commercial items.
        Schema::create('cs_service_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('customer_identifier')->nullable();
            $table->string('channel'); // chat | call | email | other
            $table->timestamp('enquiry_received_at');
            $table->timestamp('first_response_at')->nullable();
            $table->unsignedSmallInteger('response_standard_minutes'); // the applicable standard at the time, snapshotted per §8
            $table->string('resolution_status')->default('pending_internal_owner'); // first_contact_resolution | resolved_after_escalation | pending_customer | pending_internal_owner | unresolved
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('card_item_id')->nullable()->constrained('cs_card_items')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'enquiry_received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_service_interactions');
    }
};
