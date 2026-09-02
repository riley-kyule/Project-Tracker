<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave management. Entitlement is tied to the contract period by default
 * (see `leave_settings.entitlement_basis`) with a fixed number of days per
 * type — accrual is off by default, which is what lets "Contract renewed"
 * cleanly reset balances for the new period.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Org-wide toggles — a singleton row (id = 1), like company_settings.
        Schema::create('leave_settings', function (Blueprint $table) {
            $table->id();
            $table->string('entitlement_basis')->default('contract_period'); // contract_period|calendar_year
            $table->unsignedSmallInteger('leave_year_start_month')->default(1);
            $table->unsignedSmallInteger('default_annual_days')->default(21);
            $table->boolean('accrual_enabled')->default(false);
            $table->decimal('accrual_days_per_month', 5, 2)->default(1.75);
            $table->boolean('carryover_enabled')->default(false);
            $table->unsignedSmallInteger('max_carryover_days')->default(0);
            $table->boolean('block_same_department_overlap')->default(true);
            $table->jsonb('overlap_exempt_leave_type_codes')->nullable();
            $table->jsonb('overlap_override_roles')->nullable();
            $table->unsignedSmallInteger('min_notice_days')->default(0);
            $table->boolean('require_handover')->default(false);
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_paid')->default(true);
            $table->string('accrual_method')->default('entitlement'); // entitlement|monthly_accrual|none
            $table->decimal('default_days', 5, 1)->nullable(); // null = uncapped / as approved
            $table->string('gender_eligibility')->nullable(); // male|female
            $table->boolean('counts_toward_overlap_block')->default(true);
            $table->boolean('is_emergency')->default(false);
            $table->boolean('requires_document')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->unsignedSmallInteger('min_notice_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->boolean('is_recurring')->default(false); // fixed calendar date every year
            $table->string('country', 2)->default('KE');
            $table->timestamps();

            $table->index('date');
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end')->nullable();
            $table->decimal('entitled_days', 6, 2)->default(0);
            $table->decimal('carried_over_days', 6, 2)->default(0);
            $table->decimal('accrued_days', 6, 2)->default(0);
            $table->decimal('taken_days', 6, 2)->default(0);
            $table->decimal('pending_days', 6, 2)->default(0);
            $table->decimal('adjustment_days', 6, 2)->default(0);
            $table->string('adjustment_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'period_start']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('half_day_start')->default(false);
            $table->boolean('half_day_end')->default(false);
            $table->decimal('days', 5, 1);
            $table->text('reason')->nullable();
            $table->string('contact_during_leave')->nullable();
            $table->foreignId('handover_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_emergency')->default(false);
            $table->string('status')->default('pending'); // pending|approved|rejected|cancelled|withdrawn
            $table->foreignId('current_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('overlap_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('overlap_override_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'start_date']);
        });

        Schema::create('leave_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->string('action'); // approved|rejected|returned
            $table->text('note')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_approvals');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('leave_settings');
    }
};
