<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The commercial attribution ledger — Customer Service Board
        // Requirements Specification v1.0 §3/§4: every new-sale, renewal or
        // reactivation an employee records, with the payment reference and
        // clearance status a sale must have before it counts toward a
        // target. payment_reference is unique so the same payment can never
        // be attributed twice (§4 "no duplicate sales"); a genuinely new
        // customer can hold only one 'new' category record across the whole
        // table (enforced in App\Services\Cs\CsSalesAttributionService, not
        // here, since it needs a friendly validation error rather than a
        // constraint violation) so "existing customer" renewals never get
        // classified as new after the fact.
        Schema::create('cs_sales_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // new | renewal | reactivation
            $table->string('customer_identifier'); // verified customer/profile identifier, §4
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->string('country')->nullable();
            $table->string('contact_channel')->nullable();
            $table->timestamp('contact_time')->nullable();

            $table->string('payment_reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->decimal('reporting_currency_amount', 12, 2)->nullable();
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->date('exchange_rate_date')->nullable();

            $table->string('status')->default('pending'); // pending | cleared | reversed | refunded | fraudulent
            $table->timestamp('cleared_at')->nullable();

            $table->string('attribution_type')->default('primary'); // primary | shared
            $table->foreignId('shared_with_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('split_percentage', 5, 2)->nullable();

            $table->date('week_start_date'); // the weekly target this sale is attributed against, §3
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'week_start_date', 'category', 'status']);
            $table->index(['customer_identifier', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_sales_records');
    }
};
