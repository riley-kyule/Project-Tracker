<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Service Board Requirements Specification v1.0 §3: the HOD
        // sets market-adjusted weekly targets — new paying customers,
        // new-customer revenue, renewed/reactivated customers, and
        // retained/recovered revenue — before the week begins. Kept as its
        // own table rather than folded into cs_card_items, so the HOD can set
        // targets independently of assigning weekly card items, and so a
        // target change after the period begins (audited via AuditLogger) is
        // a single clear row to reason about.
        Schema::create('cs_weekly_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->unsignedInteger('new_customers_target')->default(0);
            $table->decimal('new_customer_revenue_target', 12, 2)->default(0);
            $table->unsignedInteger('renewed_customers_target')->default(0);
            $table->decimal('retained_revenue_target', 12, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('set_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'week_start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_weekly_targets');
    }
};
