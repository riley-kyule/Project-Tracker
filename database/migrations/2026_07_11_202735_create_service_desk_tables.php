<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('name');
            $table->string('default_priority')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('priority')->unique();
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->boolean('business_hours_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_number')->nullable()->unique();
            $table->string('title');
            $table->text('description');
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->constrained('ticket_categories');
            $table->foreignId('subcategory_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('priority')->default('medium')->index();
            $table->string('impact')->default('medium');
            $table->string('urgency')->default('medium');
            $table->string('status')->default('new')->index();
            $table->string('related_system')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('resolution_method')->nullable()->index();
            $table->text('resolution_summary')->nullable();
            $table->unsignedInteger('time_spent_minutes')->default(0);
            $table->unsignedTinyInteger('satisfaction_score')->nullable();
            $table->foreignId('converted_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'assigned_to']);
            $table->index('requester_id');
        });

        Schema::create('ticket_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->constrained('users');
            $table->string('reason')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_status_history');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('ticket_categories');
    }
};
