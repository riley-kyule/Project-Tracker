<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The HOD-managed library behind Customer Service Board Requirements
        // Specification v1.0 §5/§6: what a daily/weekly item *can* be, and the
        // point band it's allowed within. Assigning one to a card
        // (cs_card_items) snapshots its own weight — editing a template here
        // never rewrites a card already built from it. Mirrors
        // seo_task_templates exactly, plus metric_type for the two weekly
        // commercial line items (§6) whose achievement is computed from sales
        // targets/records rather than a manually typed quantity.
        Schema::create('cs_task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('card_type'); // daily | weekly
            $table->string('section'); // e.g. queue_clearance, new_customers, renewals, sales_support, continuity, crm, escalation, follow_up, quality, closure
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('classification')->default('production'); // mandatory | production | scheduled | conditional | additional
            $table->unsignedSmallInteger('default_weight');
            $table->unsignedSmallInteger('min_weight');
            $table->unsignedSmallInteger('max_weight');
            $table->boolean('requires_quantity')->default(false);
            $table->string('quantity_unit')->nullable();
            $table->string('metric_type')->nullable(); // new_sales | renewal — weekly commercial items only, §3/§6
            $table->string('evidence_type')->nullable(); // url | document | screenshot | system_record | report | comment
            $table->boolean('evidence_required')->default(true);
            $table->text('completion_criteria')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['card_type', 'section', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_task_templates');
    }
};
