<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The HOD-managed library behind SEO Board Spec v1.1 §4.4/§5.1: what a
        // daily/weekly item *can* be, and the point band it's allowed within.
        // Assigning one to a card (seo_card_items) snapshots its own weight —
        // editing a template here never rewrites a card already built from it.
        Schema::create('seo_task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('card_type'); // daily | weekly
            $table->string('section'); // e.g. monitoring, production, implementation, documentation, deliverables, technical, authority, research, closure
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('classification')->default('production'); // mandatory | production | scheduled | conditional | additional
            $table->unsignedSmallInteger('default_weight');
            $table->unsignedSmallInteger('min_weight');
            $table->unsignedSmallInteger('max_weight');
            $table->boolean('requires_quantity')->default(false);
            $table->string('quantity_unit')->nullable();
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
        Schema::dropIfExists('seo_task_templates');
    }
};
