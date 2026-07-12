<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrence_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('frequency');
            $table->unsignedInteger('interval_value')->default(1);
            $table->jsonb('schedule_config')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('recurrence_rule_id')->nullable()->after('project_id')->constrained('recurrence_rules')->nullOnDelete();
            $table->foreignId('previous_recurrence_task_id')->nullable()->after('recurrence_rule_id')->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_recurrence_task_id');
            $table->dropConstrainedForeignId('recurrence_rule_id');
        });
        Schema::dropIfExists('recurrence_rules');
    }
};
