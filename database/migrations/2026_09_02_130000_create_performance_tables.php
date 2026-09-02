<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight performance management: review cycles, per-employee reviews
 * (self + manager assessment + an overall rating), and goals. Deliberately
 * minimal — assessments are free-form JSON so the shape can evolve without a
 * migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('annual'); // annual|quarterly|probation
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft'); // draft|active|calibration|closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_cycle_id')->constrained('performance_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('self_assessment')->nullable();
            $table->jsonb('manager_assessment')->nullable();
            $table->decimal('overall_rating', 3, 2)->nullable(); // 1.00 – 5.00
            $table->string('status')->default('not_started'); // not_started|self_review|manager_review|shared|acknowledged|closed
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['performance_cycle_id', 'employee_id']);
        });

        Schema::create('performance_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('performance_cycle_id')->nullable()->constrained('performance_cycles')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('weight')->nullable(); // %
            $table->string('metric')->nullable();
            $table->unsignedTinyInteger('progress_pct')->default(0);
            $table->decimal('rating', 3, 2)->nullable();
            $table->string('status')->default('active'); // draft|active|done|dropped
            $table->date('due_on')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goals');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_cycles');
    }
};
