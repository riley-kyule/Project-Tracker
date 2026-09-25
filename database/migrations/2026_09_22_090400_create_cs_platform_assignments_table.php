<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer Service Board Requirements Specification v1.0 §5.2: each
        // employee has one or more assigned platforms or country operations,
        // with an effective date, responsible HOD (resolved via the owning
        // department, same as SeoHodPanelData/Department::resolveHod — not
        // duplicated here) and a backup employee. Patterned on
        // website_assignments (which already has team=customer_service) but
        // adds country and the backup/effective-date fields that table
        // doesn't carry, since a plain team-membership pivot can't express
        // either.
        Schema::create('cs_platform_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->string('country')->nullable();
            $table->date('effective_from');
            $table->foreignId('backup_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'is_active']);
            $table->index(['website_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_platform_assignments');
    }
};
