<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Service Board Requirements Specification v1.0 §8
        // "Customer complaints": linked to the responsible interaction and an
        // HOD decision; a substantiated complaint feeds the weekly quality
        // achievement calculation and gives the HOD a documented reason to
        // reduce a specific item's score via the normal decide() reason field.
        Schema::create('cs_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_interaction_id')->nullable()->constrained('cs_service_interactions')->nullOnDelete();
            $table->text('description');
            $table->timestamp('reported_at');
            $table->string('status')->default('open'); // open | substantiated | unsubstantiated | resolved
            $table->text('hod_decision')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_complaints');
    }
};
