<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Service Board Requirements Specification v1.0 §5.1
        // "Daily sales activity records": every contact attempt across the
        // four workstreams (new customers, renewals, reactivation, payment
        // support), not only the ones that end in a cleared sale — §5.1's
        // closing line is explicit that "sending a message does not by
        // itself prove productive contact", so EWMS must distinguish
        // attempted contact, delivered contact, customer response, qualified
        // interest, registration, cleared payment, and completed activation.
        // One table, a `workstream` discriminator, and nullable
        // workstream-specific columns — the four workstreams share most of
        // their fields (identifier, contact info, payment reference, next
        // action), and the UI only ever shows the columns relevant to
        // whichever workstream is selected.
        Schema::create('cs_activity_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('workstream'); // new_customer | renewal | reactivation | payment_support
            $table->string('customer_identifier');
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete(); // "platform" — new_customer
            $table->string('country')->nullable();
            $table->string('source')->nullable(); // new_customer only
            $table->string('channel')->nullable();
            $table->timestamp('contact_time')->nullable();

            // The funnel stage §5.1 requires distinguishing, for new_customer/renewal/reactivation.
            $table->string('stage')->nullable();
            $table->text('next_action')->nullable();

            // new_customer
            $table->string('registration_status')->nullable();

            // renewal
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('contact_attempts')->nullable();
            $table->text('response')->nullable();
            $table->text('reason_for_non_renewal')->nullable();
            $table->string('renewal_status')->nullable();

            // reactivation
            $table->unsignedInteger('inactive_period_days')->nullable();
            $table->boolean('restored_service')->nullable();

            // payment_support
            $table->text('issue')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('payment_provider')->nullable();
            $table->string('escalation_owner')->nullable();
            $table->text('resolution')->nullable();
            $table->boolean('activation_confirmed')->nullable();

            // shared across new_customer / renewal / payment_support
            $table->string('payment_reference')->nullable();
            $table->foreignId('sales_record_id')->nullable()->constrained('cs_sales_records')->nullOnDelete();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'workstream', 'created_at']);
            $table->index(['customer_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_activity_records');
    }
};
