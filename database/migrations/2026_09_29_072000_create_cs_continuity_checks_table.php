<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Service Board Requirements Specification v1.0 §5.2
        // "Assigned platform continuity": the five fixed daily checks
        // (registration/login, payments/activation, listings/contact
        // access, complaints/service desk, issue follow-through). One row
        // per check per platform assignment per day — "reporting a fault is
        // not sufficient closure" is handled separately by cs_continuity_issues.
        Schema::create('cs_continuity_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_assignment_id')->nullable()->constrained('cs_platform_assignments')->nullOnDelete();
            $table->date('check_date');
            $table->string('check_type'); // registration_login | payments_activation | listings_contact_access | complaints_service_desk | issue_follow_through
            $table->string('status')->default('ok'); // ok | issue_found
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'check_date', 'check_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_continuity_checks');
    }
};
