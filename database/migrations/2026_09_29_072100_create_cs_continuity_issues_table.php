<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Service Board Requirements Specification v1.0 §5.2 "Issue
        // follow-through": "reporting a fault is not sufficient closure.
        // EWMS must retain the issue owner, first-reported time, severity,
        // expected resolution, updates and verified final outcome." Every
        // field change is tracked through the normal AuditLogger trail
        // (see CsContinuityService), which is what supplies "updates" here
        // rather than a separate log table.
        Schema::create('cs_continuity_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_assignment_id')->nullable()->constrained('cs_platform_assignments')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('owner'); // free text: who owns fixing it, not necessarily an EWMS user
            $table->string('severity')->default('medium'); // low | medium | high | critical
            $table->timestamp('first_reported_at');
            $table->date('expected_resolution')->nullable();
            $table->string('status')->default('open'); // open | resolved | known_exception | reassigned
            $table->text('final_outcome')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_continuity_issues');
    }
};
