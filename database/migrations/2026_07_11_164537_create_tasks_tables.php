<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_number')->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_column_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('primary_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('medium')->index();
            $table->date('start_date')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('actual_minutes')->default(0);
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->boolean('ceo_priority')->default(false)->index();
            $table->string('confidentiality')->default('normal');
            $table->string('work_location')->default('unspecified');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['board_id', 'board_column_id', 'position']);
            $table->index('department_id');
            $table->index('primary_assignee_id');
        });

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->default('#2478be');
            $table->timestamps();
        });

        Schema::create('task_labels', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();

            $table->primary(['task_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('labels');
        Schema::dropIfExists('tasks');
    }
};
