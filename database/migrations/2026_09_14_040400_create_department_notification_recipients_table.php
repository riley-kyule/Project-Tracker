<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additional (beyond the department's manager_id / CEO) report
        // recipient emails — configurable without a code change, per SEO
        // Board Spec v1.1 §4.2.1. Effective-dated so a past report's audience
        // can be reconstructed even after this list changes; every write is
        // also recorded in audit_logs via AuditLogger.
        Schema::create('department_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_notification_recipients');
    }
};
